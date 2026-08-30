<script setup lang="ts">
/* eslint-disable vue/no-mutating-props -- the builder edits its reactive tree in place */
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { type FieldType, type FormFieldData, type VisibilityCondition, type VisibilityOperator } from '@/types';
import { Plus, X } from '@lucide/vue';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        field: FormFieldData;
        conditionableFields: (FormFieldData & { id: number })[];
        errors?: Record<string, string>;
    }>(),
    { errors: () => ({}) },
);

const conditionError = (index: number, suffix: string) => props.errors[`conditions.${index}.${suffix}`];

const selectClass =
    'flex h-9 w-full rounded-md border border-input bg-background px-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

const OPERATORS_BY_TYPE: Record<FieldType, VisibilityOperator[]> = {
    text: ['equals', 'not_equals', 'contains', 'not_contains', 'empty', 'not_empty'],
    textarea: ['equals', 'not_equals', 'contains', 'not_contains', 'empty', 'not_empty'],
    email: ['equals', 'not_equals', 'contains', 'not_contains', 'empty', 'not_empty'],
    number: ['equals', 'not_equals', 'greater_than', 'less_than', 'empty', 'not_empty'],
    date: ['equals', 'not_equals', 'greater_than', 'less_than', 'empty', 'not_empty'],
    choice: ['equals', 'not_equals', 'empty', 'not_empty'],
    dropdown: ['equals', 'not_equals', 'empty', 'not_empty'],
    checkboxes: ['contains', 'not_contains', 'empty', 'not_empty'],
    file: ['empty', 'not_empty'],
    info: [],
};

const operatorLabels: Record<VisibilityOperator, string> = {
    equals: 'is equal to',
    not_equals: 'is not equal to',
    contains: 'contains',
    not_contains: 'does not contain',
    empty: 'is empty',
    not_empty: 'is not empty',
    greater_than: 'is greater than',
    less_than: 'is less than',
};

const VALUELESS_OPERATORS: VisibilityOperator[] = ['empty', 'not_empty'];

// Fields this field can depend on: everyone else, minus itself.
const selectableFields = computed(() => props.conditionableFields.filter((f) => f.id !== props.field.id));

const targetField = (fieldId: number) => selectableFields.value.find((f) => f.id === fieldId) ?? null;

const operatorsFor = (fieldId: number): VisibilityOperator[] => {
    const target = targetField(fieldId);
    return target ? OPERATORS_BY_TYPE[target.type] : [];
};

const mode = computed<'always' | 'visible_if' | 'hidden_if'>({
    get: () => props.field.visibility?.mode ?? 'always',
    set: (value) => {
        if (value === 'always') {
            props.field.visibility = null;
            return;
        }

        if (!props.field.visibility) {
            const first = selectableFields.value[0];
            props.field.visibility = {
                mode: value,
                logic: 'all',
                conditions: first ? [{ field_id: first.id, operator: operatorsFor(first.id)[0], value: '' }] : [],
            };
            return;
        }

        props.field.visibility.mode = value;
    },
});

const logic = computed({
    get: () => props.field.visibility?.logic ?? 'all',
    set: (value: 'all' | 'any') => {
        if (props.field.visibility) {
            props.field.visibility.logic = value;
        }
    },
});

const conditions = computed(() => props.field.visibility?.conditions ?? []);

const addCondition = () => {
    const first = selectableFields.value[0];
    if (!first || !props.field.visibility) {
        return;
    }

    props.field.visibility.conditions.push({ field_id: first.id, operator: operatorsFor(first.id)[0], value: '' });
};

const removeCondition = (index: number) => {
    props.field.visibility?.conditions.splice(index, 1);
};

const onDependsOnChange = (condition: VisibilityCondition, fieldId: number) => {
    condition.field_id = fieldId;
    const allowed = operatorsFor(fieldId);

    if (!allowed.includes(condition.operator)) {
        condition.operator = allowed[0];
    }

    condition.value = '';
};

const needsValue = (operator: VisibilityOperator) => !VALUELESS_OPERATORS.includes(operator);
</script>

<template>
    <div class="grid gap-2 rounded-md border border-dashed p-3">
        <Label class="text-muted-foreground">{{ $t('Visibility') }}</Label>

        <select v-model="mode" :class="[selectClass, errors.mode ? 'border-red-500 ring-1 ring-red-500' : '']">
            <option value="always">{{ $t('Always visible') }}</option>
            <option value="visible_if" :disabled="selectableFields.length === 0">{{ $t('Visible if') }}</option>
            <option value="hidden_if" :disabled="selectableFields.length === 0">{{ $t('Hidden if') }}</option>
        </select>
        <InputError :message="errors.mode || errors.conditions" />

        <p v-if="selectableFields.length === 0" class="text-xs text-muted-foreground">
            {{ $t('Add another question first, then come back here to set a condition.') }}
        </p>

        <template v-if="mode !== 'always' && selectableFields.length > 0">
            <div v-if="conditions.length > 1" class="flex items-center gap-2 text-sm text-muted-foreground">
                <span>{{ $t('Match') }}</span>
                <select v-model="logic" :class="[selectClass, 'w-auto']">
                    <option value="all">{{ $t('all conditions') }}</option>
                    <option value="any">{{ $t('any condition') }}</option>
                </select>
            </div>

            <div v-for="(condition, index) in conditions" :key="index" class="grid gap-1">
                <div class="flex flex-wrap items-center gap-2">
                    <select
                        :value="condition.field_id"
                        :class="[selectClass, 'w-40', conditionError(index, 'field_id') ? 'border-red-500 ring-1 ring-red-500' : '']"
                        @change="onDependsOnChange(condition, Number(($event.target as HTMLSelectElement).value))"
                    >
                        <option v-for="option in selectableFields" :key="option.id" :value="option.id">{{ option.label || $t('Untitled') }}</option>
                    </select>

                    <select
                        v-model="condition.operator"
                        :class="[selectClass, 'w-44', conditionError(index, 'operator') ? 'border-red-500 ring-1 ring-red-500' : '']"
                    >
                        <option v-for="operator in operatorsFor(condition.field_id)" :key="operator" :value="operator">
                            {{ $t(operatorLabels[operator]) }}
                        </option>
                    </select>

                    <select
                        v-if="needsValue(condition.operator) && targetField(condition.field_id)?.options?.choices"
                        v-model="condition.value"
                        :class="[selectClass, 'w-40', conditionError(index, 'value') ? 'border-red-500 ring-1 ring-red-500' : '']"
                    >
                        <option v-for="choice in targetField(condition.field_id)?.options?.choices" :key="choice" :value="choice">
                            {{ choice }}
                        </option>
                    </select>
                    <input
                        v-else-if="needsValue(condition.operator)"
                        v-model="condition.value"
                        :type="
                            targetField(condition.field_id)?.type === 'date'
                                ? 'date'
                                : targetField(condition.field_id)?.type === 'number'
                                  ? 'number'
                                  : 'text'
                        "
                        :class="[
                            'flex h-9 w-40 rounded-md border border-input bg-background px-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                            conditionError(index, 'value') ? 'border-red-500 ring-1 ring-red-500' : '',
                        ]"
                    />

                    <Button
                        variant="ghost"
                        size="icon"
                        class="h-8 w-8 shrink-0 text-muted-foreground"
                        :disabled="conditions.length <= 1"
                        :aria-label="$t('Remove')"
                        @click="removeCondition(index)"
                    >
                        <X class="h-4 w-4" />
                    </Button>
                </div>
                <InputError :message="conditionError(index, 'field_id') || conditionError(index, 'operator') || conditionError(index, 'value')" />
            </div>

            <Button variant="outline" size="sm" class="w-fit" @click="addCondition">
                <Plus class="mr-1 h-4 w-4" />
                {{ $t('Add condition') }}
            </Button>
        </template>
    </div>
</template>
