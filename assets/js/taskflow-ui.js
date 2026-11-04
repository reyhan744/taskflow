/**
 * TaskFlow – shared UI components (badges, modal, toasts, confirm,
 * empty state, skeleton, pagination).
 */
(function () {
        'use strict';

        var store = window.TaskFlowStore;
        var t = function (key, args) { return window.TaskFlowI18n.t(key, args); };

        /** Status badge. */
        var TfBadgeStatus = {
                name: 'TfBadgeStatus',
                props: { status: { type: String, required: true } },
                computed: {
                        label: function () { return store.statusLabel(this.status); },
                        klass: function () { return 'tf-badge tf-badge--status-' + this.status; }
                },
                template: '<span :class="klass">{{ label }}</span>'
        };

        /** Priority badge. */
        var TfBadgePriority = {
                name: 'TfBadgePriority',
                props: { priority: { type: String, required: true } },
                computed: {
                        label: function () { return store.priorityLabel(this.priority); },
                        klass: function () { return 'tf-badge tf-badge--priority-' + this.priority; }
                },
                template: '<span :class="klass"><span class="tf-dot"></span>{{ label }}</span>'
        };

        /** Avatar + name. */
        var TfUser = {
                name: 'TfUser',
                props: { user: { type: Object, default: null }, fallback: { type: String, default: '' } },
                template:
                        '<span class="tf-user" v-if="user">' +
                                '<img class="tf-user__avatar" :src="user.avatar" alt="" loading="lazy" />' +
                                '<span class="tf-user__name">{{ user.name }}</span>' +
                        '</span>' +
                        '<span class="tf-user tf-user--empty" v-else>{{ fallback || t("task_unassigned") }}</span>',
                computed: { t: function () { return t; } }
        };

        /** Generic modal. */
        var TfModal = {
                name: 'TfModal',
                props: {
                        title: { type: String, default: '' },
                        size: { type: String, default: 'md' } // sm | md | lg
                },
                emits: ['close'],
                mounted: function () {
                        var self = this;

                        this._escHandler = function (event) {
                                if ('Escape' === event.key) { self.$emit('close'); }
                        };

                        document.addEventListener('keydown', this._escHandler);
                },
                unmounted: function () {
                        document.removeEventListener('keydown', this._escHandler);
                },
                template:
                        '<transition name="tf-fade">' +
                                '<div class="tf-modal-backdrop" @mousedown.self="$emit(\'close\')">' +
                                        '<div class="tf-modal" :class="\'tf-modal--\' + size" role="dialog" aria-modal="true">' +
                                                '<div class="tf-modal__head">' +
                                                        '<h2 class="tf-modal__title">{{ title }}</h2>' +
                                                        '<button type="button" class="tf-icon-btn" @click="$emit(\'close\')" :aria-label="t(\'close\')">' +
                                                                '<span class="dashicons dashicons-no-alt"></span>' +
                                                        '</button>' +
                                                '</div>' +
                                                '<div class="tf-modal__body"><slot /></div>' +
                                                '<div class="tf-modal__foot" v-if="$slots.foot"><slot name="foot" /></div>' +
                                        '</div>' +
                                '</div>' +
                        '</transition>',
                computed: { t: function () { return t; } }
        };

        /** Toast host. */
        var TfToasts = {
                name: 'TfToasts',
                data: function () { return { state: store.state }; },
                template:
                        '<transition-group name="tf-toast" tag="div" class="tf-toasts" aria-live="polite">' +
                                '<div v-for="item in state.toasts" :key="item.id" class="tf-toast" :class="\'tf-toast--\' + item.type">' +
                                        '<span class="dashicons" :class="\'success\' === item.type ? \'dashicons-yes\' : \'warning\' === item.type ? \'dashicons-warning\' : \'dashicons-info\'"></span>' +
                                        '<span class="tf-toast__text">{{ item.message }}</span>' +
                                '</div>' +
                        '</transition-group>'
        };

        /** Confirm dialog host. */
        var TfConfirm = {
                name: 'TfConfirm',
                data: function () { return { state: store.state }; },
                template:
                        '<transition name="tf-fade">' +
                                '<div class="tf-modal-backdrop tf-modal-backdrop--thin" v-if="state.confirmState" @mousedown.self="resolve(false)">' +
                                        '<div class="tf-modal tf-modal--sm" role="alertdialog" aria-modal="true">' +
                                                '<div class="tf-modal__body tf-confirm">' +
                                                        '<span class="dashicons dashicons-warning tf-confirm__icon"></span>' +
                                                        '<p class="tf-confirm__text">{{ state.confirmState.message }}</p>' +
                                                        '<div class="tf-confirm__actions">' +
                                                                '<button type="button" class="button" @click="resolve(false)">{{ t("cancel") }}</button>' +
                                                                '<button type="button" class="button" :class="state.confirmState.danger ? \'tf-btn-danger\' : \'tf-btn-primary\'" @click="resolve(true)">{{ t("confirm") }}</button>' +
                                                        '</div>' +
                                                '</div>' +
                                        '</div>' +
                                '</div>' +
                        '</transition>',
                methods: {
                        resolve: function (answer) { store.resolveConfirm(answer); },
                        t: function (key, args) { return t(key, args); }
                }
        };

        /** Empty state. */
        var TfEmpty = {
                name: 'TfEmpty',
                props: {
                        icon: { type: String, default: 'dashicons-portfolio' },
                        text: { type: String, required: true }
                },
                template:
                        '<div class="tf-empty">' +
                                '<span class="dashicons" :class="icon"></span>' +
                                '<p class="tf-empty__text">{{ text }}</p>' +
                                '<div class="tf-empty__actions" v-if="$slots.default"><slot /></div>' +
                        '</div>'
        };

        /** Loading skeleton rows. */
        var TfSkeleton = {
                name: 'TfSkeleton',
                props: { rows: { type: Number, default: 5 } },
                template:
                        '<div class="tf-skeleton" aria-hidden="true">' +
                                '<div class="tf-skeleton__row" v-for="row in rows" :key="row">' +
                                        '<span class="tf-skeleton__bar tf-skeleton__bar--avatar"></span>' +
                                        '<span class="tf-skeleton__bar"></span>' +
                                        '<span class="tf-skeleton__bar tf-skeleton__bar--short"></span>' +
                                '</div>' +
                        '</div>'
        };

        /** Error box with retry. */
        var TfError = {
                name: 'TfError',
                props: { message: { type: String, required: true } },
                emits: ['retry'],
                template:
                        '<div class="tf-error" role="alert">' +
                                '<span class="dashicons dashicons-warning"></span>' +
                                '<p>{{ message }}</p>' +
                                '<button type="button" class="button" @click="$emit(\'retry\')" v-if="$attrs.onRetry">{{ t("retry") }}</button>' +
                        '</div>',
                computed: { t: function () { return t; } }
        };

        /** Pagination footer. */
        var TfPagination = {
                name: 'TfPagination',
                props: {
                        page: { type: Number, required: true },
                        totalPages: { type: Number, required: true },
                        total: { type: Number, required: true },
                        perPage: { type: Number, required: true }
                },
                emits: ['go'],
                computed: {
                        from: function () { return 0 === this.total ? 0 : (this.page - 1) * this.perPage + 1; },
                        to: function () { return Math.min(this.total, this.page * this.perPage); },
                        pages: function () {
                                var list = [];
                                var start = Math.max(1, this.page - 2);
                                var end = Math.min(this.totalPages, this.page + 2);

                                for (var i = start; i <= end; i++) { list.push(i); }

                                return list;
                        },
                        t: function () { return t; }
                },
                template:
                        '<div class="tf-pagination" v-if="totalPages > 1">' +
                                '<span class="tf-pagination__info">{{ t("showing") }} {{ from }}–{{ to }} {{ t("of") }} {{ total }}</span>' +
                                '<div class="tf-pagination__buttons">' +
                                        '<button type="button" class="button" :disabled="page <= 1" @click="$emit(\'go\', page - 1)">{{ t("previous") }}</button>' +
                                        '<button v-for="p in pages" :key="p" type="button" class="button" :class="{ \'tf-btn-primary\': p === page }" @click="$emit(\'go\', p)">{{ p }}</button>' +
                                        '<button type="button" class="button" :disabled="page >= totalPages" @click="$emit(\'go\', page + 1)">{{ t("next") }}</button>' +
                                '</div>' +
                        '</div>'
        };

        window.TaskFlowUi = {
                TfBadgeStatus: TfBadgeStatus,
                TfBadgePriority: TfBadgePriority,
                TfUser: TfUser,
                TfModal: TfModal,
                TfToasts: TfToasts,
                TfConfirm: TfConfirm,
                TfEmpty: TfEmpty,
                TfSkeleton: TfSkeleton,
                TfError: TfError,
                TfPagination: TfPagination
        };
})();
