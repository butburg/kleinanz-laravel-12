<script lang="ts">
    import TextLink from '@/components/TextLink.svelte';
    import { Button } from '@/components/ui/button';
    import AuthLayout from '@/layouts/AuthLayout.svelte';
    import { Form } from '@inertiajs/svelte';
    import { CircleCheck, Inbox, LoaderCircle } from 'lucide-svelte';
    import { onMount } from 'svelte';

    interface Props {
        status?: string;
        verificationExpiresInHours: number;
    }

    let { status, verificationExpiresInHours }: Props = $props();
    let cooldown = $state(0);

    onMount(() => {
        const interval = window.setInterval(() => {
            if (cooldown > 0) cooldown -= 1;
        }, 1000);

        return () => window.clearInterval(interval);
    });

    function startCooldown() {
        cooldown = 30;
    }
</script>

<svelte:head>
    <title>Verify Email</title>
</svelte:head>

<AuthLayout title="Verify email" description="Please verify your email address by clicking on the link we just emailed to you.">
    {#if status === 'verification-link-sent'}
        <div class="flex items-start gap-3 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800 dark:border-green-900/60 dark:bg-green-950/30 dark:text-green-300" role="status">
            <CircleCheck class="mt-0.5 size-5 shrink-0" />
            <p>A new verification link has been sent to your registration email.</p>
        </div>
    {/if}

    <div class="flex items-center gap-3 rounded-lg border bg-card/50 p-4 text-sm text-muted-foreground">
        <Inbox class="size-5 shrink-0 text-primary" />
        <p>
            Check your inbox and spam folder. The link expires in {verificationExpiresInHours}
            {verificationExpiresInHours === 1 ? 'hour' : 'hours'} for your security.
        </p>
    </div>

    <Form method="post" action={route('verification.send')} className="space-y-5 text-center" onSuccess={startCooldown}>
        {#snippet children({ processing }: { processing: boolean })}
            <Button
                type="submit"
                disabled={processing || cooldown > 0}
                class="w-full shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md"
            >
                {#if processing}
                    <LoaderCircle class="h-4 w-4 animate-spin" />
                {/if}
                {processing ? 'Sending link...' : cooldown > 0 ? `Resend available in ${cooldown}s` : 'Resend verification email'}
            </Button>

            <TextLink href={route('logout')} method="post" as="button" class="mx-auto block text-sm text-muted-foreground transition-colors hover:text-foreground">
                Log out
            </TextLink>
        {/snippet}
    </Form>
</AuthLayout>
