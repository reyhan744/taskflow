/**
 * TaskFlow – application shell: header, navigation, notifications bell,
 * view routing, drawer + global hosts (toasts, confirm, task form).
 */
(function () {
	'use strict';

	var store = window.TaskFlowStore;
	var api = window.TaskFlowApi;
	var ui = window.TaskFlowUi;
	var views = window.TaskFlowViews;
	var t = function (key, args) { return window.TaskFlowI18n.t(key, args); };

	// ---------------------------------------------------------------- Bell

	var TfBell = {
		name: 'TfBell',
		components: { TfEmpty: ui.TfEmpty },
		data: function () { return { state: store.state }; },
		computed: {
			t: function () { return t; },
			ago: function () { return store.timeAgo; }
		},
		methods: {
			toggle: async function () {
				this.state.bellOpen = !this.state.bellOpen;

				if (this.state.bellOpen) {
					try {
						await store.loadNotifications();
					} catch (err) { /* keep old list */ }
				}
			},
			markAll: async function () {
				try {
					await api.post('/notifications/read', {});
					await store.loadNotifications();
				} catch (err) {
					store.toast(err.message || t('error_generic'), 'error');
				}
			},
			open: function (item) {
				this.state.bellOpen = false;

				if (item.task_id) {
					if (!parseInt(item.is_read, 10)) {
						api.post('/notifications/read', { ids: [item.id] })
							.then(function () { return store.loadNotifications(); })
							.catch(function () {});
					}

					store.openTask(item.task_id);
				}
			}
		},
		template: '#tf-tpl-bell'
	};

	// ---------------------------------------------------------------- Timer chip

	var TfTimerChip = {
		name: 'TfTimerChip',
		data: function () { return { state: store.state, elapsed: 0, tickId: 0 }; },
		computed: {
			label: function () { return store.formatDuration(this.elapsed); },
			t: function () { return t; }
		},
		mounted: function () {
			var self = this;

			this.sync();

			this.tickId = window.setInterval(function () { self.elapsed += 1; }, 1000);
			this._onChange = function () { self.sync(); };
			window.addEventListener('taskflow:tasks-changed', this._onChange);
		},
		unmounted: function () {
			window.clearInterval(this.tickId);
			window.removeEventListener('taskflow:tasks-changed', this._onChange);
		},
		methods: {
			sync: async function () {
				try {
					var timer = await store.loadActiveTimer();

					if (timer) {
						var started = new Date(String(timer.started_at).replace(' ', 'T')).getTime();

						this.elapsed = Math.max(0, Math.floor((Date.now() - started) / 1000));
					} else {
						this.elapsed = 0;
					}
				} catch (err) { /* ignore */ }
			},
			stop: async function () {
				try {
					await api.post('/time/stop');
					store.state.activeTimer = null;
					this.elapsed = 0;
					store.toast(t('timer_running') + ': ' + t('stop_timer'));
					window.dispatchEvent(new CustomEvent('taskflow:tasks-changed'));
				} catch (err) {
					store.toast(err.message || t('error_generic'), 'error');
				}
			}
		},
		template: '#tf-tpl-timer'
	};

	// ---------------------------------------------------------------- App

	var TfApp = {
		name: 'TfApp',
		components: {
			TfBell: TfBell,
			TfTimerChip: TfTimerChip,
			TfToasts: ui.TfToasts,
			TfConfirm: ui.TfConfirm,
			TfDashboard: views.TfDashboard,
			TfTasks: views.TfTasks,
			TfKanban: views.TfKanban,
			TfTaskForm: views.TfTaskForm,
			TfTaskDrawer: views.TfTaskDrawer,
			TfProjects: views.TfProjects,
			TfActivity: views.TfActivity,
			TfPayments: views.TfPayments,
			TfMail: views.TfMail,
			TfSettings: views.TfSettings
		},
		data: function () { return { state: store.state }; },
		computed: {
			caps: function () { return window.TaskFlowBoot.caps; },
			route: function () { return this.state.route; },
			currentView: function () {
				var map = {
					dashboard: 'TfDashboard',
					tasks: 'TfTasks',
					kanban: 'TfKanban',
					projects: 'TfProjects',
					activity: 'TfActivity',
					payments: 'TfPayments',
					mail: 'TfMail',
					settings: 'TfSettings'
				};

				return map[this.route] || 'TfDashboard';
			},
			tabs: function () {
				var list = [
					{ key: 'dashboard', label: t('dashboard'), icon: 'dashicons-chart-bar' },
					{ key: 'tasks', label: t('tasks'), icon: 'dashicons-list-view' },
					{ key: 'kanban', label: t('kanban'), icon: 'dashicons-exerpt-view' },
					{ key: 'projects', label: t('projects'), icon: 'dashicons-category' },
					{ key: 'activity', label: t('activity'), icon: 'dashicons-backup' }
				];

				if (this.caps.manage) {
					list.push({ key: 'payments', label: t('payments_mock'), icon: 'dashicons-money-alt' });
				}

				if (this.caps.manage || this.caps.edit_others) {
					list.push({ key: 'mail', label: t('mail_mock'), icon: 'dashicons-email-alt' });
				}

				if (this.caps.manage) {
					list.push({ key: 'settings', label: t('settings'), icon: 'dashicons-admin-generic' });
				}

				return list;
			},
			t: function () { return t; }
		},
		mounted: function () {
			var self = this;

			this.applyHash();
			this._hashChange = function () { self.applyHash(); };
			window.addEventListener('hashchange', this._hashChange);

			store.loadNotifications().catch(function () {});

			this._pollId = window.setInterval(function () {
				store.loadNotifications().catch(function () {});
			}, 45000);

			store.state.ready = true;
		},
		unmounted: function () {
			window.clearInterval(this._pollId);
			window.removeEventListener('hashchange', this._hashChange);
		},
		methods: {
			applyHash: function () {
				var hash = String(window.location.hash || '').replace('#/', '');
				var allowed = this.tabs.map(function (tab) { return tab.key; });

				this.state.route = -1 !== allowed.indexOf(hash) ? hash : 'dashboard';
			},
			go: function (route) { store.go(route); }
		},
		template: '#tf-tpl-app'
	};

	var mount = document.getElementById('taskflow-app');

	if (mount) {
		document.addEventListener('DOMContentLoaded', function () {
			Vue.createApp(TfApp).mount('#taskflow-app');
		});
	}
})();
