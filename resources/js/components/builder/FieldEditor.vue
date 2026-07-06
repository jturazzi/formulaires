<script setup lang="ts">
/* eslint-disable vue/no-mutating-props -- the builder edits its reactive tree in place */
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type FieldType, type FormFieldData } from '@/types';
import { trans } from 'laravel-vue-i18n';
import { GripVertical, Plus, Trash2, X } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    field: FormFieldData;
    maxUploadKb: number;
}>();

const emit = defineEmits<{
    (e: 'remove'): void;
}>();

const typeLabels: Record<FieldType, string> = {
    text: 'Short text',
    textarea: 'Long text',
    email: 'Email',
    number: 'Number',
    date: 'Date',
    choice: 'Single choice',
    checkboxes: 'Multiple choice',
    dropdown: 'Dropdown',
    file: 'File upload',
    info: 'Text block',
};

const hasChoices = computed(() => ['choice', 'checkboxes', 'dropdown'].includes(props.field.type));

const choices = computed(() => props.field.options?.choices ?? []);

const ensureOptions = () => {
    if (!props.field.options) {
        props.field.options = {};
    }

    if (!props.field.options.choices) {
        props.field.options.choices = [];
    }

    return props.field.options;
};

const addChoice = () => {
    const options = ensureOptions();
    options.choices!.push(`${trans('Option')} ${options.choices!.length + 1}`);
};

const removeChoice = (index: number) => {
    ensureOptions().choices!.splice(index, 1);
};
</script>

<template>
    <div class="rounded-lg border bg-background p-4">
        <div class="flex items-start gap-3">
            <button type="button" class="drag-handle mt-2 cursor-grab text-muted-foreground hover:text-foreground" :aria-label="$t('Reorder')">
                <GripVertical class="h-4 w-4" />
            </button>

            <div class="grid flex-1 gap-3">
                <div class="flex items-center justify-between gap-2">
                    <span class="rounded bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground">
                        {{ $t(typeLabels[field.type]) }}
                    </span>
                    <Button variant="ghost" size="icon" class="h-8 w-8 text-muted-foreground hover:text-red-600" @click="emit('remove')">
                        <Trash2 class="h-4 w-4" />
                    </Button>
                </div>

                <div class="grid gap-2">
                    <Label>{{ field.type === 'info' ? $t('Text') : $t('Question') }}</Label>
                    <textarea
                        v-if="field.type === 'info'"
                        v-model="field.label"
                        rows="3"
                        class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        :placeholder="$t('Text shown to respondents')"
                    ></textarea>
                    <Input v-else v-model="field.label" :placeholder="$t('Your question')" />
                </div>

                <div v-if="field.type !== 'info'" class="grid gap-2">
                    <Label class="text-muted-foreground">{{ $t('Help text (optional)') }}</Label>
                    <Input v-model="field.description" :placeholder="$t('Shown under the question')" />
                </div>

                <div v-if="hasChoices" class="grid gap-2">
                    <Label>{{ $t('Choices') }}</Label>
                    <div v-for="(choice, index) in choices" :key="index" class="flex items-center gap-2">
                        <Input v-model="field.options!.choices![index]" class="h-9" />
                        <Button
                            variant="ghost"
                            size="icon"
                            class="h-8 w-8 shrink-0 text-muted-foreground"
                            :disabled="choices.length <= 1"
                            @click="removeChoice(index)"
                        >
                            <X class="h-4 w-4" />
                        </Button>
                    </div>
                    <Button variant="outline" size="sm" class="w-fit" @click="addChoice">
                        <Plus class="mr-1 h-4 w-4" />
                        {{ $t('Add choice') }}
                    </Button>
                </div>

                <p v-if="field.type === 'file'" class="text-xs text-muted-foreground">
                    {{ $t('Respondents can upload one file (max :size MB).', { size: String(Math.round(maxUploadKb / 1024)) }) }}
                </p>

                <div v-if="field.type !== 'info'" class="flex items-center gap-2">
                    <Checkbox :id="`required-${field.id ?? 'new'}`" v-model:checked="field.required" />
                    <Label :for="`required-${field.id ?? 'new'}`" class="font-normal">{{ $t('Required') }}</Label>
                </div>
            </div>
        </div>
    </div>
</template>
