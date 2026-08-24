<script setup>
import { computed, ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { faChevronLeft, faChevronRight } from '@fortawesome/free-solid-svg-icons'
import { resolveBilingualField } from '../../../Composables/useBilingualContent.js'

const { t, locale } = useI18n()

const props = defineProps({
  items: {
    type: Array,
    required: true,
    default: () => []
  },
  collapsed: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['toggle'])

const page = usePage()
const currentRoute = computed(() => page.url)

const companyName = computed(() =>
  resolveBilingualField(page.props.companyInfo, 'name', locale.value) || t('sidebar.adminPanel')
)
const companyLogo = computed(() => page.props.companyInfo?.logo || page.props.companyInfo?.attachment?.asset_path || null)
const companyInitial = computed(() => companyName.value.trim().charAt(0).toUpperCase() || 'A')

const isCollapsed = computed(() => props.collapsed)

const expandedItems = ref(new Set())

const itemKey = (item) => item.id || item.route || item.route || item.label || item.label

const isAlwaysOpenSection = (item) => (
  item?.alwaysOpen === true || item?.type === 'section' || item?.type === 'section'
)

const itemLabel = (item) => item?.label || item?.label || ''

const itemHref = (item) => item?.route || item?.route || null

const toggleExpand = (item) => {
  if (isAlwaysOpenSection(item)) {
    return
  }

  const key = itemKey(item)
  if (expandedItems.value.has(key)) {
    expandedItems.value.delete(key)
  } else {
    expandedItems.value.add(key)
  }
}

const isExpanded = (item) => isAlwaysOpenSection(item) || expandedItems.value.has(itemKey(item))

const handleToggle = () => {
  emit('toggle')
}

const getRoutePath = (routeUrl) => {
  if (!routeUrl) return ''
  try {
    // If it's a full URL, extract pathname
    const url = new URL(routeUrl, window.location.origin)
    return url.pathname
  } catch {
    // If it's already a path, return as is
    return routeUrl
  }
}

const isActiveRoute = (item) => {
  const currentPath = getRoutePath(currentRoute.value)
  const itemPath = getRoutePath(itemHref(item))

  if (itemPath && (currentPath === itemPath || (itemPath !== '/' && currentPath.startsWith(`${itemPath}/`)))) return true

  if (item.children && item.children.length > 0) {
    return item.children.some(child => isActiveRoute(child))
  }

  return false
}

const navigateTo = (path) => {
  router.visit(path)
}

const handleItemClick = (item) => {
  if (item.disabled || item.disabled || isAlwaysOpenSection(item)) {
    return
  }

  const clickHandler = item.onClick || item.onClick
  if (typeof clickHandler === 'function') {
    clickHandler()
    if (window.innerWidth < 768) {
      emit('toggle')
    }
    return
  }

  if (item.children && item.children.length > 0) {
    toggleExpand(item)
    return
  }

  const href = itemHref(item)
  if (href) {
    navigateTo(href)
    if (window.innerWidth < 768) {
      emit('toggle')
    }
  }
}

const collectActiveParentKeys = (items, currentPath, keys) => {
  items.forEach((item) => {
    if (isAlwaysOpenSection(item)) {
      return
    }

    if (item.children && item.children.length > 0) {
      const hasActiveChild = item.children.some(child => isActiveRoute(child))
      if (hasActiveChild) {
        keys.add(itemKey(item))
        collectActiveParentKeys(item.children, currentPath, keys)
      }
    }
  })
}

const syncExpandedFromRoute = () => {
  const keys = new Set()
  collectActiveParentKeys(props.items, getRoutePath(currentRoute.value), keys)
  expandedItems.value = keys
}

watch(
  [currentRoute, () => props.items.length],
  () => {
    syncExpandedFromRoute()
  },
  { immediate: true }
)

</script>

<template>
  <!-- Mobile Backdrop -->
  <div
    v-if="!isCollapsed"
    @click="handleToggle"
    class="fixed inset-0 bg-black/60 bg-opacity-50 z-30 md:hidden transition-opacity"
  ></div>

  <!-- Sidebar -->
  <aside
    :class="[
      'fixed start-0 top-0 h-screen bg-gray-900 border-e border-gray-800 flex flex-col transition-all duration-300',
      'z-40',
      // Mobile: slide in/out from start side
      'md:translate-x-0',
      isCollapsed ? '-translate-x-full rtl:translate-x-full md:translate-x-0 md:rtl:translate-x-0 md:w-20' : 'translate-x-0 w-64'
    ]"
  >
    <!-- Logo Area -->
    <div class="flex items-center justify-between px-4 py-4 border-b border-gray-800">
      <div v-if="!isCollapsed" class="flex items-center gap-3 min-w-0">
        <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center overflow-hidden shrink-0">
          <img v-if="companyLogo" :src="companyLogo" :alt="companyName" class="h-full w-full object-cover" />
          <span v-else class="text-white font-bold text-sm">{{ companyInitial }}</span>
        </div>
        <span class="text-card-title text-gray-100 truncate">{{ companyName }}</span>
      </div>
      <div v-else class="mx-auto">
        <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center overflow-hidden">
          <img v-if="companyLogo" :src="companyLogo" :alt="companyName" class="h-full w-full object-cover" />
          <span v-else class="text-white font-bold text-sm">{{ companyInitial }}</span>
        </div>
      </div>
      <button
        @click="handleToggle"
        class="text-gray-300 hover:text-white cursor-pointer transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 rounded-lg p-1"
        :aria-label="isCollapsed ? t('sidebar.expandSidebar') : t('sidebar.collapseSidebar')"
        :aria-expanded="!isCollapsed"
      >
        <font-awesome-icon
          :icon="isCollapsed ? faChevronRight : faChevronLeft"
          class="w-5 h-5 transition-transform duration-300"
        />
      </button>
    </div>

    <!-- Navigation Menu -->
    <nav class="flex-1 px-3 py-4 overflow-y-auto">
      <ul class="space-y-4">
        <li v-for="item in items" :key="item.id || item.route || item.route" class="relative">
          <div v-if="isAlwaysOpenSection(item)" class="space-y-1.5">
            <p
              v-if="!isCollapsed"
              class="px-3 py-2.5 mt-2 mb-2 text-base font-bold tracking-wide text-white pointer-events-none rounded-lg bg-gray-800 ring-1 ring-gray-600"
            >
              {{ itemLabel(item) }}
            </p>
            <ul class="space-y-1 ms-2 ps-2 border-s border-gray-800">
              <li v-for="child in item.children" :key="child.id || child.route || child.route">
                <button
                  type="button"
                  :disabled="child.disabled || child.disabled"
                  @click="handleItemClick(child)"
                  :class="[
                    'w-full group relative flex items-center gap-2.5 px-2.5 py-2 rounded-lg transition-all duration-200 text-start min-h-11',
                    (child.disabled || child.disabled)
                      ? 'text-amber-400 cursor-default'
                      : 'cursor-pointer hover:scale-[1.01] active:scale-[0.99]',
                    !(child.disabled || child.disabled) && (isActiveRoute(child) || child.current)
                      ? 'bg-blue-900 text-white shadow-md'
                      : !(child.disabled || child.disabled)
                        ? 'text-gray-300 hover:bg-gray-800 hover:text-gray-100'
                        : ''
                  ]"
                  :aria-label="itemLabel(child)"
                  :title="isCollapsed ? itemLabel(child) : ''"
                >
                  <span v-if="child.current && !isCollapsed" class="shrink-0 text-sm">✓</span>
                  <span
                    v-if="child.icon"
                    class="shrink-0 w-4 flex justify-center"
                  >
                    <font-awesome-icon :icon="child.icon" class="w-4 h-4" />
                  </span>
                  <span
                    v-if="!isCollapsed"
                    class="flex-1 text-sm whitespace-nowrap overflow-hidden font-normal truncate"
                  >
                    {{ itemLabel(child) }}
                  </span>
                </button>
              </li>
            </ul>
          </div>
          <template v-else>
          <!-- Parent Item -->
          <div
            @click="handleItemClick(item)"
            :class="[
              'group relative flex items-center gap-3 px-3 py-2.5 rounded-lg cursor-pointer transition-all duration-200',
              'hover:scale-[1.02] active:scale-[0.98]',
              isActiveRoute(item)
                ? 'bg-blue-900 text-white shadow-md'
                : 'text-gray-300 hover:bg-gray-800 hover:text-gray-100'
            ]"
            :aria-label="item.label"
            :aria-expanded="item.children ? isExpanded(item) : undefined"
            :title="isCollapsed ? item.label : ''"
          >
            <!-- Icon -->
            <span :class="[
              'shrink-0 transition-transform duration-200',
              isActiveRoute(item) && !item.children ? 'scale-110' : 'group-hover:scale-110'
            ]">
              <font-awesome-icon
                :icon="item.icon"
                class="w-5 h-5"
              />
            </span>

            <!-- Label -->
            <span
              v-if="!isCollapsed"
              :class="[
                'flex-1 text-body whitespace-nowrap overflow-hidden font-medium',
                isActiveRoute(item) ? 'font-semibold' : ''
              ]"
            >
              {{ item.label }}
            </span>

            <!-- Expand/Collapse Indicator -->
            <span
              v-if="!isCollapsed && item.children && item.children.length > 0"
              :class="[
                'shrink-0 transition-all duration-300',
                isExpanded(item) ? 'rotate-90' : '',
                isActiveRoute(item) ? 'opacity-100' : 'opacity-60 group-hover:opacity-100'
              ]"
            >
              <font-awesome-icon :icon="faChevronRight" class="w-3.5 h-3.5" />
            </span>

            <!-- Active Indicator Line -->
            <div
              v-if="isActiveRoute(item) && !isCollapsed"
              class="absolute start-0 top-1/2 -translate-y-1/2 w-1 h-8 bg-blue-500 rounded-e-full"
            ></div>
          </div>

          <!-- Child Items (Submenu) with Slide Animation -->
          <transition
            enter-active-class="transition-all duration-300 ease-out"
            enter-from-class="opacity-0 -translate-y-2 max-h-0"
            enter-to-class="opacity-100 translate-y-0 max-h-[70vh]"
            leave-active-class="transition-all duration-200 ease-in"
            leave-from-class="opacity-100 translate-y-0 max-h-[70vh]"
            leave-to-class="opacity-0 -translate-y-2 max-h-0"
          >
            <ul
              v-if="!isCollapsed && item.children && item.children.length > 0 && isExpanded(item)"
              class="mt-1.5 space-y-0.5 ms-2 overflow-hidden"
            >
              <li v-for="(child, index) in item.children" :key="child.id || child.route" class="relative">
                <!-- Connecting Line -->
                <div
                  class="absolute start-[13px] top-0 w-px bg-gray-700/50"
                  :class="[
                    index === 0 ? 'h-1/2 top-1/2' : 'h-full',
                    index === item.children.length - 1 ? 'h-1/2' : ''
                  ]"
                ></div>

                <div
                  v-if="child.children && child.children.length > 0"
                  @click.stop="handleItemClick(child)"
                  :class="[
                    'group relative flex items-center gap-2.5 px-3 py-2 rounded-lg cursor-pointer transition-all duration-200',
                    'hover:scale-[1.01] active:scale-[0.99] ms-3',
                    isActiveRoute(child)
                      ? 'bg-blue-900 text-white'
                      : 'text-gray-300 hover:bg-gray-800/50 hover:text-gray-100  border-transparent hover:border-gray-600'
                  ]"
                  :aria-label="child.label"
                  :aria-expanded="isExpanded(child)"
                >
                  <span class="shrink-0">
                    <font-awesome-icon v-if="child.icon" :icon="child.icon" class="w-3.5 h-3.5" />
                  </span>
                  <span :class="['text-muted whitespace-nowrap overflow-hidden flex-1', isActiveRoute(child) ? 'font-medium' : 'font-normal']">
                    {{ child.label }}
                  </span>
                  <font-awesome-icon
                    :icon="faChevronRight"
                    :class="['w-3 h-3 shrink-0 transition-transform', isExpanded(child) ? 'rotate-90' : '']"
                  />
                </div>
                <a
                  v-else
                  :href="child.route"
                  @click.prevent="navigateTo(child.route)"
                  :class="[
                    'group relative flex items-center gap-2.5 px-3 py-2 rounded-lg cursor-pointer transition-all duration-200',
                    'hover:scale-[1.01] active:scale-[0.99] ms-3',
                    isActiveRoute(child)
                      ? 'bg-blue-900 text-white'
                      : 'text-gray-300 hover:bg-gray-800/50 hover:text-gray-100  border-transparent hover:border-gray-600'
                  ]"
                  :aria-label="child.label"
                >
                  <!-- Child Icon (optional) or Bullet Point -->
                  <span :class="[
                    'shrink-0 transition-transform duration-200',
                    currentRoute === child.route ? 'scale-110' : 'group-hover:scale-110'
                  ]">
                    <font-awesome-icon
                      v-if="child.icon"
                      :icon="child.icon"
                      class="w-3.5 h-3.5"
                    />
                    <span v-else class="w-3.5 h-3.5 flex items-center justify-center">
                      <span :class="[
                        'w-1 h-1 rounded-full transition-all duration-200',
                        currentRoute === child.route
                          ? 'bg-blue-400 w-1.5 h-1.5'
                          : 'bg-gray-500 group-hover:bg-blue-400 group-hover:w-1.5 group-hover:h-1.5'
                      ]"></span>
                    </span>
                  </span>

                  <!-- Child Label -->
                  <span :class="[
                    'text-muted whitespace-nowrap overflow-hidden',
                    currentRoute === child.route ? 'font-medium' : 'font-normal'
                  ]">
                    {{ child.label }}
                  </span>

                  <!-- Hover indicator -->
                  <span
                    v-if="isActiveRoute(child) && !child.children"
                    class="absolute end-2 w-1 h-1 rounded-full bg-blue-400 animate-pulse"
                  ></span>
                </a>
                <ul
                  v-if="!isCollapsed && child.children && child.children.length > 0 && isExpanded(child)"
                  class="mt-1 mb-1 space-y-0.5 ms-4"
                >
                  <li v-for="grandchild in child.children" :key="grandchild.id || grandchild.route">
                    <a
                      :href="grandchild.route"
                      @click.prevent="navigateTo(grandchild.route)"
                      :class="[
                        'group relative flex items-center gap-2.5 px-3 py-2 rounded-lg cursor-pointer transition-all duration-200 ms-3',
                        isActiveRoute(grandchild)
                          ? 'bg-blue-900 text-white'
                          : 'text-gray-300 hover:bg-gray-800/50 hover:text-gray-100'
                      ]"
                      :aria-label="grandchild.label"
                    >
                      <font-awesome-icon v-if="grandchild.icon" :icon="grandchild.icon" class="w-3.5 h-3.5 shrink-0" />
                      <span :class="['text-muted whitespace-nowrap overflow-hidden', isActiveRoute(grandchild) ? 'font-medium' : 'font-normal']">
                        {{ grandchild.label }}
                      </span>
                    </a>
                  </li>
                </ul>
              </li>
            </ul>
          </transition>
          </template>
        </li>
      </ul>
    </nav>

    <!-- Footer (Optional User Info) -->
    <div class="px-3 py-4 border-t border-gray-800">
      <div
        :class="[
          'flex items-center gap-3 px-3 py-2 rounded-lg text-gray-300',
          !isCollapsed ? '' : 'justify-center'
        ]"
      >
        <div class="w-8 h-8 rounded-full bg-linear-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white font-semibold shrink-0">
          A
        </div>
        <div v-if="!isCollapsed" class="overflow-hidden">
          <p class="text-label text-gray-100 truncate">{{ page.props.auth.user.name }}</p>
          <p class="text-muted muted-color truncate">{{ page.props.auth.user.email }}</p>
        </div>
      </div>
    </div>
  </aside>
</template>
