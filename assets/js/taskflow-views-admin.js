/**
 * TaskFlow – admin views: Projects, Activity feed, Mock Payments, Mock Mail,
 * Settings.
 */
(function () {
        'use strict';

        var store = window.TaskFlowStore;
        var api = window.TaskFlowApi;
        var ui = window.TaskFlowUi;
        var t = function (key, args) { return window.TaskFlowI18n.t(key, args); };

        // ---------------------------------------------------------------- Projects

        var TfProjects = {
                name: 'TfProjects',
                components: { TfModal: ui.TfModal, TfEmpty: ui.TfEmpty, TfSkeleton: ui.TfSkeleton, TfError: ui.TfError },
                data: function () {
                        return {
                                projects: [],
                                stats: null,
                                loading: true,
                                error: '',
                                saving: false,
                                formOpen: false,
                                editId: 0,
                                form: { name: '', description: '', color: '#2271b1' }
                        };
                },
                computed: {
                        isEdit: function () { return this.editId > 0; },
                        formTitle: function () { return this.isEdit ? t('edit_project') : t('new_project'); },
                        canManage: function () { return window.TaskFlowBoot.caps.manage || window.TaskFlowBoot.caps.edit_others; },
                        statFor: function () {
                                var self = this;

                                return function (projectId) {
                                        if (!self.stats) { return { total: 0, completed: 0 }; }

                                        var found = self.stats.filter(function (row) { return parseInt(row.id, 10) === parseInt(projectId, 10); })[0];

                                        return found ? { total: found.total, completed: found.completed } : { total: 0, completed: 0 };
                                };
                        },
                        t: function () { return t; }
                },
                mounted: function () { this.load(); },
                methods: {
                        load: async function () {
                                this.loading = true;
                                this.error = '';

                                try {
                                        var data = await api.get('/projects');

                                        this.projects = data.items || [];

                                        var dash = await api.get('/dashboard');

                                        this.stats = (dash.stats && dash.stats.by_project) || [];
                                        store.state.projects = this.projects;
                                        store.state.projectsLoaded = true;
                                } catch (err) {
                                        this.error = err.message || t('error_generic');
                                }

                                this.loading = false;
                        },
                        openCreate: function () {
                                this.editId = 0;
                                this.form = { name: '', description: '', color: '#2271b1' };
                                this.formOpen = true;
                        },
                        openEdit: function (project) {
                                this.editId = project.id;
                                this.form = {
                                        name: project.name,
                                        description: project.description || '',
                                        color: project.color || '#2271b1'
                                };
                                this.formOpen = true;
                        },
                        save: async function () {
                                if (!String(this.form.name).trim()) { return; }

                                this.saving = true;

                                try {
                                        var result;

                                        if (this.isEdit) {
                                                result = await api.put('/projects/' + this.editId, this.form);
                                        } else {
                                                result = await api.post('/projects', this.form);
                                        }

                                        store.toast(result.message || t('app_title'));
                                        this.formOpen = false;
                                        this.load();
                                } catch (err) {
                                        store.toast(err.message || t('error_generic'), 'error');
                                }

                                this.saving = false;
                        },
                        remove: async function (project) {
                                var ok = await store.confirm(t('delete_project_confirm'), { danger: true });

                                if (!ok) { return; }

                                try {
                                        var result = await api.del('/projects/' + project.id);

                                        store.toast(result.message || t('app_title'));
                                        this.load();
                                } catch (err) {
                                        store.toast(err.message || t('error_generic'), 'error');
                                }
                        },
                        filterBy: function (project) {
                                store.go('tasks');
                                window.setTimeout(function () {
                                        window.dispatchEvent(new CustomEvent('taskflow:filter-project', { detail: project.id }));
                                }, 60);
                        }
                },
                template: '#tf-tpl-projects'
        };

        // ---------------------------------------------------------------- Activity

        var TfActivity = {
                name: 'TfActivity',
                components: { TfUser: ui.TfUser, TfEmpty: ui.TfEmpty, TfSkeleton: ui.TfSkeleton, TfError: ui.TfError },
                data: function () {
                        return { items: [], page: 1, totalPages: 1, loading: true, error: '' };
                },
                computed: {
                        fmtDate: function () { return store.formatDate; },
                        t: function () { return t; }
                },
                mounted: function () { this.load(1); },
                methods: {
                        load: async function (page) {
                                this.loading = true;
                                this.error = '';

                                try {
                                        var data = await api.get('/activity?page=' + (page || 1) + '&per_page=20');

                                        this.items = data.items || [];
                                        this.totalPages = data.total_pages || 1;
                                        this.page = data.page || 1;
                                } catch (err) {
                                        this.error = err.message || t('error_generic');
                                }

                                this.loading = false;
                        },
                        openTask: function (id) { if (id) { store.openTask(id); } },
                        icon: function (type) {
                                var icons = {
                                        created: 'dashicons-plus-alt',
                                        updated: 'dashicons-edit',
                                        title_changed: 'dashicons-edit',
                                        priority_changed: 'dashicons-leftright',
                                        due_changed: 'dashicons-calendar-alt',
                                        project_changed: 'dashicons-category',
                                        assigned: 'dashicons-admin-users',
                                        status_changed: 'dashicons-update',
                                        comment_added: 'dashicons-admin-comments',
                                        time_logged: 'dashicons-clock',
                                        deleted: 'dashicons-trash',
                                        mock_payment: 'dashicons-money-alt',
                                        project_created: 'dashicons-category'
                                };

                                return icons[type] || 'dashicons-marker';
                        }
                },
                template: '#tf-tpl-activity'
        };

        // ---------------------------------------------------------------- Mock payments

        var TfPayments = {
                name: 'TfPayments',
                components: { TfModal: ui.TfModal, TfEmpty: ui.TfEmpty, TfSkeleton: ui.TfSkeleton, TfError: ui.TfError },
                data: function () {
                        return {
                                items: [],
                                totals: null,
                                currency: 'USD',
                                statusFilter: '',
                                loading: true,
                                error: '',
                                formOpen: false,
                                saving: false,
                                form: { amount: '', currency: 'USD', order_ref: '', simulate: 'success' }
                        };
                },
                computed: {
                        fmtDate: function () { return store.formatDate; },
                        totalRows: function () {
                                var totals = [
                                        { key: 'success', label: t('payment_status_success'), value: this.totals ? this.totals.success : 0, tone: 'green' },
                                        { key: 'failed', label: t('payment_status_failed'), value: this.totals ? this.totals.failed : 0, tone: 'red' },
                                        { key: 'pending', label: t('payment_status_pending'), value: this.totals ? this.totals.pending : 0, tone: 'amber' }
                                ];

                                return totals;
                        },
                        t: function () { return t; }
                },
                mounted: function () {
                        this.form.currency = window.TaskFlowBoot.settings.currency || 'USD';
                        this.load();
                },
                methods: {
                        load: async function () {
                                this.loading = true;
                                this.error = '';

                                try {
                                        var query = this.statusFilter ? '?status=' + this.statusFilter : '';
                                        var data = await api.get('/mock/payments' + query);

                                        this.items = data.items || [];
                                        this.totals = data.totals || null;
                                        this.currency = data.currency || this.currency;
                                } catch (err) {
                                        this.error = err.message || t('error_generic');
                                }

                                this.loading = false;
                        },
                        create: async function () {
                                if (!this.form.amount || parseFloat(this.form.amount) <= 0) {
                                        store.toast(t('error_generic'), 'error');
                                        return;
                                }

                                this.saving = true;

                                try {
                                        var result = await api.post('/mock/payments', this.form);

                                        store.toast(result.message || t('app_title'));
                                        this.formOpen = false;
                                        this.form.amount = '';
                                        this.form.order_ref = '';
                                        this.load();
                                } catch (err) {
                                        store.toast(err.message || t('error_generic'), 'error');
                                }

                                this.saving = false;
                        },
                        finalize: async function (payment, result) {
                                try {
                                        var done = await api.post('/mock/payments/' + payment.id + '/finalize', { result: result });

                                        store.toast(done.message || t('app_title'));
                                        this.load();
                                } catch (err) {
                                        store.toast(err.message || t('error_generic'), 'error');
                                }
                        },
                        money: function (row) {
                                var amount = parseFloat(row.amount || 0);

                                return amount.toLocaleString() + ' ' + (row.currency || this.currency);
                        },
                        statusLabel: function (status) {
                                if ('success' === status) { return t('payment_status_success'); }
                                if ('failed' === status) { return t('payment_status_failed'); }

                                return t('payment_status_pending');
                        }
                },
                template: '#tf-tpl-payments'
        };

        // ---------------------------------------------------------------- Mock mail

        var TfMail = {
                name: 'TfMail',
                components: { TfModal: ui.TfModal, TfEmpty: ui.TfEmpty, TfSkeleton: ui.TfSkeleton, TfError: ui.TfError },
                data: function () {
                        return {
                                tab: 'outbox',
                                outbox: [],
                                inbox: [],
                                loading: true,
                                error: '',
                                composeOpen: false,
                                simulateOpen: false,
                                sending: false,
                                compose: { to: '', subject: '', body: '' },
                                simulate: { from_email: '', from_name: '', subject: '', body: '' },
                                viewing: null
                        };
                },
                computed: {
                        items: function () { return 'inbox' === this.tab ? this.inbox : this.outbox; },
                        fmtDate: function () { return store.formatDate; },
                        t: function () { return t; }
                },
                mounted: function () { this.load(); },
                methods: {
                        load: async function () {
                                this.loading = true;
                                this.error = '';

                                try {
                                        var out = await api.get('/mock/email/outbox?per_page=50');
                                        var inc = await api.get('/mock/email/inbox?per_page=50');

                                        this.outbox = out.items || [];
                                        this.inbox = inc.items || [];
                                } catch (err) {
                                        this.error = err.message || t('error_generic');
                                }

                                this.loading = false;
                        },
                        send: async function () {
                                this.sending = true;

                                try {
                                        var result = await api.post('/mock/email/send', this.compose);

                                        store.toast(result.message || t('app_title'));
                                        this.composeOpen = false;
                                        this.compose = { to: '', subject: '', body: '' };
                                        this.load();
                                } catch (err) {
                                        store.toast(err.message || t('error_generic'), 'error');
                                }

                                this.sending = false;
                        },
                        simulate: async function () {
                                this.sending = true;

                                try {
                                        var result = await api.post('/mock/email/inbox', this.simulate);

                                        store.toast(result.message || t('app_title'));
                                        this.simulateOpen = false;
                                        this.simulate = { from_email: '', from_name: '', subject: '', body: '' };
                                        this.tab = 'inbox';
                                        this.load();
                                } catch (err) {
                                        store.toast(err.message || t('error_generic'), 'error');
                                }

                                this.sending = false;
                        },
                        markRead: async function (message) {
                                try {
                                        await api.post('/mock/email/inbox/' + message.id + '/read');
                                        message.is_read = 1;
                                } catch (err) {
                                        store.toast(err.message || t('error_generic'), 'error');
                                }
                        },
                        open: function (message) {
                                this.viewing = message;

                                if ('inbox' === this.tab && !parseInt(message.is_read, 10)) {
                                        this.markRead(message);
                                }
                        }
                },
                template: '#tf-tpl-mail'
        };

        // ---------------------------------------------------------------- Settings

        var TfSettings = {
                name: 'TfSettings',
                components: { TfSkeleton: ui.TfSkeleton, TfError: ui.TfError },
                data: function () {
                        return {
                                loading: true,
                                error: '',
                                saving: false,
                                settings: null,
                                roles: [],
                                system: null,
                                importText: '',
                                importing: false
                        };
                },
                computed: {
                        canManage: function () { return window.TaskFlowBoot.caps.manage; },
                        knownRoles: function () {
                                var self = this;

                                return this.roles.filter(function (role) {
                                        return -1 === ['administrator', 'editor'].indexOf(role.key);
                                });
                        },
                        t: function () { return t; }
                },
                mounted: function () {
                        if (!this.canManage) {
                                this.loading = false;
                                this.error = t('error_forbidden');
                                return;
                        }

                        this.load();
                },
                methods: {
                        load: async function () {
                                this.loading = true;
                                this.error = '';

                                try {
                                        var data = await api.get('/settings');

                                        this.settings = data.settings;
                                        this.roles = data.roles || [];

                                        this.system = (await api.get('/system')).system || null;
                                } catch (err) {
                                        this.error = err.message || t('error_generic');
                                }

                                this.loading = false;
                        },
                        save: async function () {
                                this.saving = true;

                                try {
                                        var result = await api.post('/settings', this.settings);

                                        this.settings = result.settings;
                                        store.toast(result.message || t('settings_saved'));
                                } catch (err) {
                                        store.toast(err.message || t('error_generic'), 'error');
                                }

                                this.saving = false;
                        },
                        exportData: function (format) {
                                var stamp = new Date().toISOString().slice(0, 10);

                                api.download('/export?format=' + format, 'taskflow-export-' + stamp + '.' + format)
                                        .catch(function (err) { store.toast(err.message || t('error_generic'), 'error'); });
                        },
                        downloadTemplate: function () {
                                api.download('/import/sample', 'taskflow-import-template.csv')
                                        .catch(function (err) { store.toast(err.message || t('error_generic'), 'error'); });
                        },
                        onFilePicked: function (event) {
                                var self = this;
                                var file = event.target.files && event.target.files[0];

                                if (!file) { return; }

                                var reader = new FileReader();

                                reader.onload = function () {
                                        self.importText = String(reader.result || '');
                                        store.toast(t('settings_import') + ' ✓', 'info', 1600);
                                };

                                reader.readAsText(file, 'utf-8');
                                event.target.value = '';
                        },
                        runImport: async function () {
                                if (!String(this.importText).trim()) { return; }

                                this.importing = true;

                                try {
                                        var result = await api.post('/import', { csv: this.importText });

                                        store.toast(result.message || t('app_title'), 'info', 6000);

                                        if (result.report && result.report.errors && result.report.errors.length) {
                                                window.console && window.console.warn('TaskFlow import errors:', result.report.errors);
                                        }
                                } catch (err) {
                                        store.toast(err.message || t('error_generic'), 'error');
                                }

                                this.importing = false;
                        },
                        seed: async function () {
                                try {
                                        var result = await api.post('/seed');

                                        store.toast(result.message || t('app_title'));
                                } catch (err) {
                                        store.toast(err.message || t('error_generic'), 'error');
                                }
                        },
                        toggle: function (key) {
                                if (this.settings) { this.settings[key] = !this.settings[key]; }
                        },
                        hasRole: function (key) {
                                return this.settings && this.settings.extra_edit_roles && -1 !== this.settings.extra_edit_roles.indexOf(key);
                        },
                        toggleRole: function (key) {
                                if (!this.settings) { return; }

                                var list = (this.settings.extra_edit_roles || []).slice();
                                var index = list.indexOf(key);

                                if (-1 === index) { list.push(key); }
                                else { list.splice(index, 1); }

                                this.settings.extra_edit_roles = list;
                        }
                },
                template: '#tf-tpl-settings'
        };

        window.TaskFlowViews = window.TaskFlowViews || {};
        window.TaskFlowViews.TfProjects = TfProjects;
        window.TaskFlowViews.TfActivity = TfActivity;
        window.TaskFlowViews.TfPayments = TfPayments;
        window.TaskFlowViews.TfMail = TfMail;
        window.TaskFlowViews.TfSettings = TfSettings;
})();
