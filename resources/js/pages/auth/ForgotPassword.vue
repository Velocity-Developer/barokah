<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Forgot password',
        description: 'Enter your email to receive a password reset link',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Forgot password" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <div class="space-y-6">
        <Form v-bind="email.form()" v-slot="{ errors, processing }">
            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    autocomplete="off"
                    autofocus
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="my-6 flex items-center justify-start">
                <Button
                    class="w-full text-white"
                    style="
                        --btn-bg: var(--brand-primary, #ee4d2d);
                        --btn-border: var(--brand-primary, #ee4d2d);
                        --btn-bg-hover: var(--brand-primary-hover, #d73211);
                        --btn-border-hover: var(--brand-primary-hover, #d73211);
                        background-color: var(--btn-bg);
                        border-color: var(--btn-border);
                        border-width: 1px;
                        transition: background-color 150ms ease, border-color 150ms ease;
                    "
                    @mouseenter="($event.currentTarget as HTMLElement).style.setProperty('--btn-bg','var(--btn-bg-hover)'); ($event.currentTarget as HTMLElement).style.setProperty('--btn-border','var(--btn-border-hover)')"
                    @mouseleave="($event.currentTarget as HTMLElement).style.setProperty('--btn-bg','var(--brand-primary, #ee4d2d)'); ($event.currentTarget as HTMLElement).style.setProperty('--btn-border','var(--brand-primary, #ee4d2d)')"
                    :disabled="processing"
                    data-test="email-password-reset-link-button"
                >
                    <Spinner v-if="processing" />
                    Email password reset link
                </Button>
            </div>
        </Form>

        <div class="text-muted-foreground space-x-1 text-center text-sm">
            <span>Or, return to</span>
            <TextLink :href="login()">log in</TextLink>
        </div>
    </div>
</template>
