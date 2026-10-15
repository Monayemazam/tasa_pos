/**
 * Tasa POS - Central Icon Registry & Mapping
 * Adheres strictly to Design & UX Guideline §3.8
 * Lineicons outline style only, 1.5px stroke.
 */

export type IconConcept =
    | 'dashboard'
    | 'sales'
    | 'cart'
    | 'receipt'
    | 'invoices'
    | 'customers'
    | 'inventory'
    | 'products'
    | 'leather'
    | 'accessories'
    | 'adjustments'
    | 'expenses'
    | 'payroll'
    | 'reports'
    | 'settings'
    | 'courier'
    | 'users'
    | 'user'
    | 'shield'
    | 'printer'
    | 'download'
    | 'search'
    | 'plus'
    | 'edit'
    | 'trash'
    | 'check'
    | 'check-circle'
    | 'close'
    | 'alert'
    | 'warning'
    | 'danger'
    | 'info'
    | 'calendar'
    | 'filter'
    | 'phone'
    | 'location'
    | 'chevron-down'
    | 'chevron-up'
    | 'chevron-left'
    | 'chevron-right'
    | 'advance'
    | 'spinner'
    | 'menu'
    | 'more'
    | 'logout'
    | 'box'
    | 'refresh'
    | 'alert-circle'
    | 'database'
    | 'archive'
    | 'user-check'
    | 'file-text'
    | 'clock'
    | 'external-link'
    | 'x'
    | 'alert-triangle'
    | 'eye'
    | 'history'
    | 'arrow-left'
    | 'table'
    | 'bar-chart';

export const ICON_CLASS_MAP: Record<IconConcept, string> = {
    box: 'lni lni-box-archive-1',
    refresh: 'lni lni-refresh-circle-1-clockwise',
    dashboard: 'lni lni-dashboard-square-1',
    sales: 'lni lni-cart-1',
    cart: 'lni lni-cart-1',
    receipt: 'lni lni-dollar',
    invoices: 'lni lni-dollar',
    customers: 'lni lni-user-4',
    inventory: 'lni lni-box-archive-1',
    products: 'lni lni-box-archive-1',
    leather: 'lni lni-layers-1',
    accessories: 'lni lni-bookmark-1',
    adjustments: 'lni lni-funnel-1',
    expenses: 'lni lni-wallet-1',
    payroll: 'lni lni-user-multiple-4',
    reports: 'lni lni-bar-chart-4',
    settings: 'lni lni-gear-1',
    courier: 'lni lni-truck-delivery-1',
    users: 'lni lni-user-multiple-4',
    user: 'lni lni-user-4',
    shield: 'lni lni-shield-2',
    printer: 'lni lni-printer',
    download: 'lni lni-download-1',
    search: 'lni lni-search-1',
    plus: 'lni lni-plus',
    edit: 'lni lni-pencil-1',
    trash: 'lni lni-trash-3',
    check: 'lni lni-check',
    'check-circle': 'lni lni-check-circle-1',
    close: 'lni lni-xmark',
    alert: 'lni lni-bell-1',
    warning: 'lni lni-bell-1',
    danger: 'lni lni-xmark-circle',
    info: 'lni lni-info',
    calendar: 'lni lni-calendar-days',
    filter: 'lni lni-funnel-1',
    phone: 'lni lni-phone',
    location: 'lni lni-map-marker-5',
    'chevron-down': 'lni lni-chevron-down',
    'chevron-up': 'lni lni-chevron-up',
    'chevron-left': 'lni lni-chevron-left',
    'chevron-right': 'lni lni-chevron-right',
    advance: 'lni lni-arrow-upward',
    spinner: 'lni lni-spinner-2-sacle animate-spin',
    menu: 'lni lni-chevron-down',
    more: 'lni lni-chevron-down-circle',
    logout: 'lni lni-exit',
    'alert-circle': 'lni lni-warning',
    database: 'lni lni-database-2',
    archive: 'lni lni-box-archive-1',
    'user-check': 'lni lni-user-4',
    'file-text': 'lni lni-document',
    clock: 'lni lni-timer-1',
    'external-link': 'lni lni-arrow-up-right',
    x: 'lni lni-xmark',
    'alert-triangle': 'lni lni-warning',
    eye: 'lni lni-eye',
    history: 'lni lni-timer-1',
    'arrow-left': 'lni lni-chevron-left',
    table: 'lni lni-grid-alt',
    'bar-chart': 'lni lni-bar-chart-4',
};

/**
 * Returns class string for the given concept.
 */
export function getIconClass(concept: IconConcept): string {
    return ICON_CLASS_MAP[concept] || 'lni lni-info';
}
