<script lang="ts">
    import UserInfo from '@/components/UserInfo.svelte';
    import { SidebarMenu, SidebarMenuItem } from '@/components/ui/sidebar';
    import { page } from '@inertiajs/svelte';

    const user = $derived($page.props.auth.user);
</script>

<SidebarMenu>
    <SidebarMenuItem>
        <div class="flex items-center gap-2 px-2 py-2 text-sidebar-accent-foreground">
            <UserInfo {user} showEmail={true} />
        </div>
    </SidebarMenuItem>
</SidebarMenu>
