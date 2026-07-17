<script lang="ts">
    import UserInfo from '@/components/UserInfo.svelte';
    import { DropdownMenuLabel } from '@/components/ui/dropdown-menu';
    import type { User } from '@/types';
    

    interface Props {
        user: User;
    }

    let { user }: Props = $props();
</script>

<DropdownMenuLabel class="p-0 font-normal">
    <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
        <UserInfo {user} showEmail={true} />
    </div>
</DropdownMenuLabel>
