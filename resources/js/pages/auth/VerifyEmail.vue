<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        title: 'Email verification',
        description:
            'Please verify your email address by clicking on the link we just emailed to you.',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Email verification" />

    <div
        v-if="status === 'verification-link-sent'"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        A new verification link has been sent to the email address you provided
        during registration.
    </div>

    <Form
        v-bind="send.form()"
        class="space-y-6 text-center"
        v-slot="{ processing }"
    >
        <Button :disabled="processing"
            class="text-white"
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
        >
            <Spinner v-if="processing" />
            Resend verification email
        </Button>

        <TextLink :href="logout()" as="button" class="mx-auto block text-sm">
            Log out
        </TextLink>
    </Form>
</template>
