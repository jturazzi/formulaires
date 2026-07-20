<?php

namespace App\Http\Controllers;

use App\Models\Answer;
use App\Models\Form;
use App\Models\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FormResponseController extends Controller
{
    public function index(Request $request, Form $form): InertiaResponse
    {
        $this->authorize('view', $form);

        $form->load('fields.section');

        $responses = $form->responses()
            ->with('answers')
            ->latest('submitted_at')
            ->paginate(25)
            ->withQueryString();

        $fields = $form->fields->filter->isInput()->values();

        return Inertia::render('forms/Responses', [
            'form' => [
                'id' => $form->id,
                'title' => $form->title,
                'status' => $form->status,
                'retention_days' => $form->effectiveRetentionDays(),
            ],
            'fields' => $fields->map(fn ($field) => [
                'id' => $field->id,
                'type' => $field->type,
                'label' => $field->label,
            ]),
            'responses' => [
                'data' => collect($responses->items())->map(fn (Response $response) => $this->responsePayload($response)),
                'meta' => [
                    'current_page' => $responses->currentPage(),
                    'last_page' => $responses->lastPage(),
                    'total' => $responses->total(),
                ],
            ],
        ]);
    }

    public function destroy(Request $request, Form $form, Response $response): RedirectResponse
    {
        $this->authorize('update', $form);

        abort_unless($response->form_id === $form->id, 404);

        $response->purge();

        return back()->with('success', __('messages.response_deleted'));
    }

    public function export(Request $request, Form $form): StreamedResponse
    {
        $this->authorize('view', $form);

        $fields = $form->fields()->where('type', '!=', 'info')->orderBy('position')->get();
        $fileName = str($form->title)->slug()->append('-reponses.csv');

        return response()->streamDownload(function () use ($form, $fields) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM so Excel opens accented characters correctly.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                __('messages.submitted_at'),
                __('messages.respondent_email'),
                ...$fields->pluck('label')->all(),
            ], ';');

            $form->responses()->with('answers')->latest('submitted_at')->lazy()->each(function (Response $response) use ($handle, $fields) {
                $answers = $response->answers->keyBy('form_field_id');

                fputcsv($handle, [
                    $response->submitted_at->format('Y-m-d H:i:s'),
                    $response->email ?? '',
                    ...$fields->map(function ($field) use ($answers) {
                        $answer = $answers->get($field->id);

                        if (! $answer) {
                            return '';
                        }

                        if ($field->type === 'file') {
                            return $answer->file_path ? route('answers.file', ['answer' => $answer, 'preview' => 1]) : '';
                        }

                        return is_array($answer->value) ? implode(', ', $answer->value) : (string) $answer->value;
                    })->all(),
                ], ';');
            });

            fclose($handle);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function downloadFile(Request $request, Answer $answer): StreamedResponse
    {
        $form = $answer->response->form;

        $this->authorize('view', $form);

        abort_unless($answer->file_path, 404);

        if ($request->boolean('preview')) {
            return Storage::disk('local')->response($answer->file_path, $answer->file_name);
        }

        return Storage::disk('local')->download($answer->file_path, $answer->file_name);
    }

    private function responsePayload(Response $response): array
    {
        return [
            'id' => $response->id,
            'email' => $response->email,
            'email_verified' => $response->email_verified_at !== null,
            'submitted_at' => $response->submitted_at,
            'consented_at' => $response->consented_at,
            'answers' => $response->answers->map(fn (Answer $answer) => [
                'field_id' => $answer->form_field_id,
                'value' => $answer->value,
                'file_name' => $answer->file_name,
                'file_size' => $answer->file_size,
                'file_url' => $answer->file_path ? route('answers.file', $answer) : null,
            ]),
        ];
    }
}
