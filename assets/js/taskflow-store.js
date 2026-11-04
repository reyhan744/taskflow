/**
 * TaskFlow – global reactive store.
 *
 * Holds shared state (route, reference data, notifications, toasts, confirm
 * dialog) plus formatting helpers used by every view.
 */
(function () {
        'use strict';

        var t = function (key, args) { return window.TaskFlowI18n.t(key, args); };
        var isFa = function () { return window.TaskFlowI18n.isFaLocale(); };

        var boot = window.TaskFlowBoot || {};

        var store = Vue.reactive({
                boot: boot,
                route: 'dashboard',
                ready: false,
                globalLoading: false,

                // Reference data.
                users: [],
                usersLoaded: false,
                projects: [],
                projectsLoaded: false,
                tags: [],
                tagsLoaded: false,

                // Notifications.
                notifications: [],
                unread: 0,
                bellOpen: false,

                // Timer.
                activeTimer: null,
                timerSeconds: 0,

                // Toasts + confirm.
                toasts: [],
                toastId: 0,
                confirmState: null,

                // Task drawer.
                drawerTaskId: 0,
                drawerOpen: false
        });

        /**
         * Show a toast message.
         *
         * @param {string} message Text.
         * @param {string} type    success|error|info.
         * @param {number} [ttl]   Auto-dismiss in ms.
         */
        function toast(message, type, ttl) {
                type = type || 'success';
                ttl = ttl || 4200;

                var id = ++store.toastId;

                store.toasts.push({ id: id, message: message, type: type });

                window.setTimeout(function () {
                        store.toasts = store.toasts.filter(function (item) { return item.id !== id; });
                }, ttl);
        }

        /**
         * Promise-based confirm dialog.
         *
         * @param {string} message Question text.
         * @param {Object} [opts]  { danger }.
         * @return {Promise<boolean>}
         */
        function confirm(message, opts) {
                opts = opts || {};

                return new Promise(function (resolve) {
                        store.confirmState = {
                                message: message,
                                danger: !!opts.danger,
                                resolve: resolve
                        };
                });
        }

        /** Resolve the confirm dialog. */
        function resolveConfirm(answer) {
                if (store.confirmState && store.confirmState.resolve) {
                        store.confirmState.resolve(answer);
                }

                store.confirmState = null;
        }

        /** Load assignable users (cached). */
        async function loadUsers() {
                if (store.usersLoaded) { return store.users; }

                var data = await window.TaskFlowApi.get('/users');

                store.users = data.items || [];
                store.usersLoaded = true;

                return store.users;
        }

        /** Load projects (cached). */
        async function loadProjects() {
                if (store.projectsLoaded) { return store.projects; }

                var data = await window.TaskFlowApi.get('/projects');

                store.projects = data.items || [];
                store.projectsLoaded = true;

                return store.projects;
        }

        /** Invalidate the projects cache (after CRUD). */
        function invalidateProjects() {
                store.projectsLoaded = false;
        }

        /** Load tags (cached). */
        async function loadTags() {
                if (store.tagsLoaded) { return store.tags; }

                var data = await window.TaskFlowApi.get('/tags');

                store.tags = data.items || [];
                store.tagsLoaded = true;

                return store.tags;
        }

        /** Invalidate the tags cache. */
        function invalidateTags() {
                store.tagsLoaded = false;
        }

        /** Load the current user's notifications. */
        async function loadNotifications() {
                var data = await window.TaskFlowApi.get('/notifications');

                store.notifications = data.items || [];
                store.unread = data.unread || 0;

                return data;
        }

        /** Load the running timer of the current user. */
        async function loadActiveTimer() {
                var data = await window.TaskFlowApi.get('/time/active');

                store.activeTimer = data.item || null;
                store.timerSeconds = 0;

                return data.item;
        }

        // ---------- formatting helpers ----------

        var localeTag = isFa() ? 'fa-IR-u-ca-persian-nu-latn' : 'en-GB';

        /**
         * Format "Y-m-d H:i:s" for display (Persian calendar on fa locales).
         *
         * @param {string|null} value    Raw datetime.
         * @param {boolean}     [withTime] Include the time part.
         * @return {string}
         */
        function formatDate(value, withTime) {
                if (!value) { return '—'; }

                var date = new Date(String(value).replace(' ', 'T'));

                if (isNaN(date.getTime())) { return String(value); }

                var options = { year: 'numeric', month: 'short', day: 'numeric' };

                if (withTime) {
                        options.hour = '2-digit';
                        options.minute = '2-digit';
                }

                try {
                        return new Intl.DateTimeFormat(localeTag, options).format(date);
                } catch (e) {
                        return date.toLocaleString();
                }
        }

        /**
         * Relative time ("5 min ago").
         *
         * @param {string} value Raw datetime.
         * @return {string}
         */
        function timeAgo(value) {
                if (!value) { return '—'; }

                var date = new Date(String(value).replace(' ', 'T'));
                var diff = Math.max(0, Math.floor((Date.now() - date.getTime()) / 1000));

                if (diff < 60) { return t('just_now'); }
                if (diff < 3600) { return Math.floor(diff / 60) + ' ' + t('minutes_ago'); }
                if (diff < 86400) { return Math.floor(diff / 3600) + ' ' + t('hours_ago'); }

                return Math.floor(diff / 86400) + ' ' + t('days_ago');
        }

        /**
         * "1h 23m" duration formatting.
         *
         * @param {number} seconds Duration.
         * @return {string}
         */
        function formatDuration(seconds) {
                seconds = Math.max(0, parseInt(seconds, 10) || 0);
                var hours = Math.floor(seconds / 3600);
                var minutes = Math.floor((seconds % 3600) / 60);
                var rest = seconds % 60;
                var parts = [];

                if (hours) { parts.push(hours + 'h'); }
                if (minutes) { parts.push(minutes + 'm'); }
                if (!hours && (rest || !parts.length)) { parts.push(rest + 's'); }

                return parts.join(' ') || '0s';
        }

        /** Status label. */
        function statusLabel(status) {
                return t('status_' + status);
        }

        /** Priority label. */
        function priorityLabel(priority) {
                return t('priority_' + priority);
        }

        /** Navigate between views. */
        function go(route) {
                store.route = route;
                store.bellOpen = false;

                if (window.location) {
                        window.location.hash = '#/' + route;
                }
        }

        /** Open the task drawer. */
        function openTask(id) {
                store.drawerTaskId = parseInt(id, 10);
                store.drawerOpen = true;
        }

        /** Close the task drawer. */
        function closeTask() {
                store.drawerOpen = false;
                store.drawerTaskId = 0;
        }

        window.TaskFlowStore = {
                state: store,
                toast: toast,
                confirm: confirm,
                resolveConfirm: resolveConfirm,
                loadUsers: loadUsers,
                loadProjects: loadProjects,
                invalidateProjects: invalidateProjects,
                loadTags: loadTags,
                invalidateTags: invalidateTags,
                loadNotifications: loadNotifications,
                loadActiveTimer: loadActiveTimer,
                formatDate: formatDate,
                timeAgo: timeAgo,
                formatDuration: formatDuration,
                statusLabel: statusLabel,
                priorityLabel: priorityLabel,
                go: go,
                openTask: openTask,
                closeTask: closeTask
        };
})();
