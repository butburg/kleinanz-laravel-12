<script lang="ts">
    import NavMain from '@/components/NavMain.svelte';
    import NavUser from '@/components/NavUser.svelte';
    import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
    import { type NavItem } from '@/types';
    import { Link, router } from '@inertiajs/svelte';
    import { CircleHelp, FilePlus2, LayoutList, LogOut, Settings } from 'lucide-svelte';
    import AppLogo from './AppLogo.svelte';

    const mainNavItems: NavItem[] = [
        {
            title: 'Create Ad',
            href: '/dashboard',
            icon: FilePlus2,
        },
        {
            title: 'Show Ads',
            href: '/ads',
            icon: LayoutList,
        },
    ];

    const handleLogout = () => {
        router.flushAll();
    };
</script>

<Sidebar collapsible="icon" variant="inset">
    <SidebarHeader>
        <SidebarMenu>
            <SidebarMenuItem>
                <SidebarMenuButton size="lg">
                    <Link href={route('dashboard')}>
                        <AppLogo />
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarHeader>

    <SidebarContent>
        <NavMain items={mainNavItems} />
    </SidebarContent>

    <SidebarFooter>
        <SidebarMenu class="mt-auto">
            <SidebarMenuItem>
                <SidebarMenuButton class="text-neutral-600 hover:text-neutral-800 dark:text-neutral-300 dark:hover:text-neutral-100">
                    <Link href={route('support.create')} class="flex items-center gap-2 w-full">
                        <CircleHelp class="h-4 w-4 shrink-0" />
                        <span>Support</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
            <SidebarMenuItem>
                <SidebarMenuButton class="text-neutral-600 hover:text-neutral-800 dark:text-neutral-300 dark:hover:text-neutral-100">
                    <Link href={route('profile.edit')} class="flex items-center gap-2 w-full">
                        <Settings class="h-4 w-4 shrink-0" />
                        <span>Settings</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
            <SidebarMenuItem>
                <SidebarMenuButton class="text-neutral-600 hover:text-neutral-800 dark:text-neutral-300 dark:hover:text-neutral-100">
                    <Link method="post" onclick={handleLogout} href={route('logout')} as="button" class="flex items-center gap-2 w-full text-left">
                        <LogOut class="h-4 w-4 shrink-0" />
                        <span>Log out</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
        <NavUser />
    </SidebarFooter>
</Sidebar>
