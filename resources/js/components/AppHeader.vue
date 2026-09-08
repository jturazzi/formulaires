<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Sheet, SheetContent, SheetTrigger } from '@/components/ui/sheet';
import UserInfo from '@/components/UserInfo.vue';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { type NavItem, type SharedData, type User } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { ChevronDown, FileText, LayoutGrid, Menu, Settings2, Users } from '@lucide/vue';
import { computed, ref } from 'vue';

const page = usePage<SharedData>();
const user = computed(() => page.props.auth.user as User);
const mobileMenuOpen = ref(false);

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        { title: trans('Dashboard'), href: '/dashboard', icon: LayoutGrid },
        { title: trans('Forms'), href: '/forms', icon: FileText },
    ];

    if (page.props.auth.user?.role === 'admin') {
        items.push(
            { title: trans('Users'), href: '/admin/users', icon: Users },
            { title: trans('Site settings'), href: '/admin/settings', icon: Settings2 },
        );
    }

    return items;
});

const isActive = (href: string) => page.url === href || page.url.startsWith(`${href}/`);
</script>

<template>
    <header class="bg-background sticky top-0 z-20 border-b">
        <div class="mx-auto flex h-16 w-full max-w-7xl items-center gap-4 px-4 md:px-6">
            <Link href="/dashboard" class="flex items-center">
                <AppLogo />
            </Link>

            <nav class="hidden items-center gap-1 md:flex">
                <Link
                    v-for="item in mainNavItems"
                    :key="item.href"
                    :href="item.href"
                    class="flex items-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium transition-colors"
                    :class="
                        isActive(item.href)
                            ? 'bg-accent text-accent-foreground'
                            : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground'
                    "
                >
                    <component :is="item.icon" class="h-4 w-4" />
                    {{ item.title }}
                </Link>
            </nav>

            <div class="ml-auto flex items-center gap-2">
                <LanguageSwitcher class="hidden sm:flex" />

                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button variant="ghost" class="h-9 gap-2 px-2">
                            <UserInfo :user="user" />
                            <ChevronDown class="text-muted-foreground h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent class="w-56" align="end">
                        <UserMenuContent :user="user" />
                    </DropdownMenuContent>
                </DropdownMenu>

                <Sheet v-model:open="mobileMenuOpen">
                    <SheetTrigger as-child>
                        <Button variant="ghost" size="icon" class="md:hidden" :aria-label="$t('Menu')">
                            <Menu class="h-5 w-5" />
                        </Button>
                    </SheetTrigger>
                    <SheetContent side="left" class="w-64">
                        <nav class="mt-8 flex flex-col gap-1">
                            <Link
                                v-for="item in mainNavItems"
                                :key="item.href"
                                :href="item.href"
                                class="flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium"
                                :class="
                                    isActive(item.href)
                                        ? 'bg-accent text-accent-foreground'
                                        : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground'
                                "
                                @click="mobileMenuOpen = false"
                            >
                                <component :is="item.icon" class="h-4 w-4" />
                                {{ item.title }}
                            </Link>
                        </nav>
                    </SheetContent>
                </Sheet>
            </div>
        </div>
    </header>
</template>
