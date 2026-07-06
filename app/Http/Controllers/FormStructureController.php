<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\FormField;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FormStructureController extends Controller
{
    /**
     * Replace the whole structure (sections + fields) of a form in one call.
     * The builder sends the full tree; existing ids are kept so answers
     * attached to untouched fields survive the update.
     */
    public function update(Request $request, Form $form): RedirectResponse
    {
        $this->authorize('update', $form);

        $validated = $request->validate([
            'sections' => ['required', 'array', 'min:1', 'max:50'],
            'sections.*.id' => ['nullable', 'integer'],
            'sections.*.title' => ['nullable', 'string', 'max:255'],
            'sections.*.description' => ['nullable', 'string', 'max:5000'],
            'sections.*.fields' => ['present', 'array', 'max:100'],
            'sections.*.fields.*.id' => ['nullable', 'integer'],
            'sections.*.fields.*.type' => ['required', Rule::in(FormField::TYPES)],
            'sections.*.fields.*.label' => ['required', 'string', 'max:1000'],
            'sections.*.fields.*.description' => ['nullable', 'string', 'max:5000'],
            'sections.*.fields.*.required' => ['boolean'],
            'sections.*.fields.*.options' => ['nullable', 'array'],
            'sections.*.fields.*.options.choices' => ['nullable', 'array', 'max:100'],
            'sections.*.fields.*.options.choices.*' => ['required', 'string', 'max:500'],
            'sections.*.fields.*.options.multiple' => ['nullable', 'boolean'],
            'sections.*.fields.*.options.max_length' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);

        DB::transaction(function () use ($form, $validated) {
            $keptSectionIds = [];
            $keptFieldIds = [];

            foreach ($validated['sections'] as $sectionPosition => $sectionData) {
                $section = null;

                if (! empty($sectionData['id'])) {
                    $section = $form->sections()->find($sectionData['id']);
                }

                $sectionAttributes = [
                    'title' => $sectionData['title'] ?? null,
                    'description' => $sectionData['description'] ?? null,
                    'position' => $sectionPosition,
                ];

                $section = $section
                    ? tap($section)->update($sectionAttributes)
                    : $form->sections()->create($sectionAttributes);

                $keptSectionIds[] = $section->id;

                foreach ($sectionData['fields'] as $fieldPosition => $fieldData) {
                    $field = null;

                    if (! empty($fieldData['id'])) {
                        $field = $form->fields()->find($fieldData['id']);
                    }

                    $fieldAttributes = [
                        'form_section_id' => $section->id,
                        'type' => $fieldData['type'],
                        'label' => $fieldData['label'],
                        'description' => $fieldData['description'] ?? null,
                        'required' => $fieldData['required'] ?? false,
                        'options' => $fieldData['options'] ?? null,
                        'position' => $fieldPosition,
                    ];

                    $field = $field
                        ? tap($field)->update($fieldAttributes)
                        : $form->fields()->create($fieldAttributes);

                    $keptFieldIds[] = $field->id;
                }
            }

            $form->fields()->whereNotIn('id', $keptFieldIds)->get()->each->delete();
            $form->sections()->whereNotIn('id', $keptSectionIds)->get()->each->delete();
        });

        return back()->with('success', __('messages.saved'));
    }
}
