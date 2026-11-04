<?php
/**
 * Vue.js application templates (x-template blocks), part 2: projects,
 * activity feed, mock payments, mock mail, settings.
 *
 * @package TaskFlow
 */

defined( 'ABSPATH' ) || exit;
?>
<script type="text/x-template" id="tf-tpl-projects">
	<section class="tf-view">
		<div class="tf-view__head">
			<h2 class="tf-view__title">{{ t('projects') }}</h2>
			<button type="button" class="button button-primary tf-btn-primary" v-if="canManage" @click="openCreate">
				<span class="dashicons dashicons-plus-alt2"></span> {{ t('new_project') }}
			</button>
		</div>

		<tf-error v-if="error" :message="error" @retry="load"></tf-error>
		<tf-skeleton v-else-if="loading" :rows="4"></tf-skeleton>

		<template v-else>
			<tf-empty v-if="!projects.length" icon="dashicons-category" :text="t('no_projects')">
				<button type="button" class="button button-primary tf-btn-primary" v-if="canManage" @click="openCreate">{{ t('new_project') }}</button>
			</tf-empty>

			<div class="tf-project-grid" v-else>
				<article v-for="project in projects" :key="project.id" class="tf-project">
					<span class="tf-project__bar" :style="{ background: project.color }"></span>
					<div class="tf-project__body">
						<h3 class="tf-project__name">{{ project.name }}</h3>
						<p class="tf-project__desc" v-if="project.description">{{ project.description }}</p>
						<div class="tf-project__stats" v-if="statFor(project.id).total">
							<div class="tf-project__track">
								<div class="tf-project__fill" :style="{ width: Math.round(100 * statFor(project.id).completed / statFor(project.id).total) + '%' }"></div>
							</div>
							<span class="tf-project__count">{{ statFor(project.id).completed }}/{{ statFor(project.id).total }} {{ t('total_tasks') }}</span>
						</div>
						<span class="tf-muted" v-else>{{ t('no_tasks') }}</span>
					</div>
					<div class="tf-project__actions">
						<button type="button" class="button-link" @click="filterBy(project)">{{ t('tasks') }}</button>
						<button type="button" class="button-link" v-if="canManage" @click="openEdit(project)">{{ t('edit') }}</button>
						<button type="button" class="button-link tf-link-danger" v-if="canManage" @click="remove(project)">{{ t('delete') }}</button>
					</div>
				</article>
			</div>
		</template>

		<tf-modal :title="formTitle" @close="formOpen = false" v-if="formOpen">
			<form class="tf-form" @submit.prevent="save">
				<div class="tf-form__row">
					<label class="tf-form__label">{{ t('project_name') }} *</label>
					<input type="text" v-model="form.name" class="tf-input" required />
				</div>
				<div class="tf-form__row">
					<label class="tf-form__label">{{ t('task_description') }}</label>
					<textarea v-model="form.description" class="tf-input" rows="3"></textarea>
				</div>
				<div class="tf-form__row tf-form__row--inline">
					<label class="tf-form__label">{{ t('project_color') }}</label>
					<input type="color" v-model="form.color" class="tf-color" />
				</div>
				<div class="tf-form__foot">
					<button type="button" class="button" @click="formOpen = false">{{ t('cancel') }}</button>
					<button type="submit" class="button button-primary tf-btn-primary" :disabled="saving">{{ saving ? t('loading') : t('save') }}</button>
				</div>
			</form>
		</tf-modal>
	</section>
</script>

<script type="text/x-template" id="tf-tpl-activity">
	<section class="tf-view">
		<div class="tf-view__head">
			<h2 class="tf-view__title">{{ t('activity') }}</h2>
		</div>

		<tf-error v-if="error" :message="error" @retry="load()"></tf-error>
		<tf-skeleton v-else-if="loading" :rows="6"></tf-skeleton>

		<template v-else>
			<tf-empty v-if="!items.length" icon="dashicons-backup" :text="t('no_activity')"></tf-empty>

			<div class="tf-panel" v-else>
				<ul class="tf-feed tf-feed--large">
					<li v-for="event in items" :key="event.id" class="tf-feed__item" :class="{ 'tf-feed__item--link': event.task_id }" @click="openTask(event.task_id)">
						<span class="tf-feed__icon tf-feed__icon--boxed"><span class="dashicons" :class="icon(event.type)"></span></span>
						<div class="tf-feed__body">
							<p class="tf-feed__msg">{{ event.message }}</p>
							<span class="tf-feed__meta">
								<span v-if="event.user">{{ event.user.name }}</span>
								<time>{{ fmtDate(event.created_at, true) }}</time>
							</span>
						</div>
					</li>
				</ul>
			</div>

			<div class="tf-pagination" v-if="totalPages > 1">
				<span></span>
				<div class="tf-pagination__buttons">
					<button type="button" class="button" :disabled="page <= 1" @click="load(page - 1)">{{ t('previous') }}</button>
					<button type="button" class="button" :disabled="page >= totalPages" @click="load(page + 1)">{{ t('next') }}</button>
				</div>
			</div>
		</template>
	</section>
</script>

<script type="text/x-template" id="tf-tpl-payments">
	<section class="tf-view">
		<div class="tf-view__head">
			<h2 class="tf-view__title">{{ t('payments_title') }}</h2>
			<button type="button" class="button button-primary tf-btn-primary" @click="formOpen = true">
				<span class="dashicons dashicons-plus-alt2"></span> {{ t('new_payment') }}
			</button>
		</div>

		<div class="tf-mock-banner">
			<span class="dashicons dashicons-info-outline"></span>
			{{ t('mock_notice') }}
		</div>

		<tf-error v-if="error" :message="error" @retry="load"></tf-error>
		<tf-skeleton v-else-if="loading" :rows="5"></tf-skeleton>

		<template v-else>
			<div class="tf-stats tf-stats--3">
				<div v-for="row in totalRows" :key="row.key" class="tf-stat" :class="'tf-stat--' + row.tone">
					<div class="tf-stat__meta">
						<span class="tf-stat__value">{{ money({ amount: row.value }) }}</span>
						<span class="tf-stat__label">{{ row.label }}</span>
					</div>
				</div>
			</div>

			<div class="tf-toolbar">
				<select v-model="statusFilter" @change="load" class="tf-select">
					<option value="">{{ t('task_status') }}: {{ t('all') }}</option>
					<option value="success">{{ t('payment_status_success') }}</option>
					<option value="failed">{{ t('payment_status_failed') }}</option>
					<option value="pending">{{ t('payment_status_pending') }}</option>
				</select>
			</div>

			<tf-empty v-if="!items.length" icon="dashicons-money-alt" :text="t('no_payments')"></tf-empty>

			<div class="tf-table-wrap" v-else>
				<table class="tf-table">
					<thead>
						<tr>
							<th>{{ t('payment_reference') }}</th>
							<th>{{ t('payment_order') }}</th>
							<th>{{ t('payment_amount') }}</th>
							<th>{{ t('task_status') }}</th>
							<th>{{ t('created_at') }}</th>
							<th class="tf-col-actions"></th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="payment in items" :key="payment.id">
							<td><code class="tf-code">{{ payment.gateway_ref }}</code></td>
							<td><code class="tf-code">{{ payment.order_ref }}</code></td>
							<td><strong>{{ money(payment) }}</strong></td>
							<td>
								<span class="tf-badge" :class="'tf-badge--pay-' + payment.status">{{ statusLabel(payment.status) }}</span>
							</td>
							<td>{{ fmtDate(payment.created_at, true) }}</td>
							<td class="tf-col-actions" v-if="'pending' === payment.status">
								<button type="button" class="button button-small tf-btn-success" @click="finalize(payment, 'success')">{{ t('payment_finalize_success') }}</button>
								<button type="button" class="button button-small tf-btn-danger" @click="finalize(payment, 'failed')">{{ t('payment_finalize_failed') }}</button>
							</td>
							<td class="tf-col-actions" v-else></td>
						</tr>
					</tbody>
				</table>
			</div>
		</template>

		<tf-modal :title="t('new_payment')" @close="formOpen = false" v-if="formOpen">
			<form class="tf-form" @submit.prevent="create">
				<div class="tf-form__grid">
					<div class="tf-form__row">
						<label class="tf-form__label">{{ t('payment_amount') }} *</label>
						<input type="number" step="0.01" min="0.01" v-model="form.amount" class="tf-input" required />
					</div>
					<div class="tf-form__row">
						<label class="tf-form__label">{{ t('payment_currency') }}</label>
						<input type="text" v-model="form.currency" class="tf-input" maxlength="8" />
					</div>
					<div class="tf-form__row">
						<label class="tf-form__label">{{ t('payment_order_ref') }}</label>
						<input type="text" v-model="form.order_ref" class="tf-input" />
					</div>
					<div class="tf-form__row">
						<label class="tf-form__label">{{ t('payment_simulate') }}</label>
						<select v-model="form.simulate" class="tf-input">
							<option value="success">{{ t('payment_status_success') }}</option>
							<option value="failed">{{ t('payment_status_failed') }}</option>
							<option value="pending">{{ t('payment_status_pending') }}</option>
						</select>
					</div>
				</div>
				<div class="tf-form__foot">
					<button type="button" class="button" @click="formOpen = false">{{ t('cancel') }}</button>
					<button type="submit" class="button button-primary tf-btn-primary" :disabled="saving">{{ saving ? t('loading') : t('create') }}</button>
				</div>
			</form>
		</tf-modal>
	</section>
</script>

<script type="text/x-template" id="tf-tpl-mail">
	<section class="tf-view">
		<div class="tf-view__head">
			<h2 class="tf-view__title">{{ t('mail_title') }}</h2>
			<div class="tf-view__buttons">
				<button type="button" class="button" @click="simulateOpen = true">
					<span class="dashicons dashicons-download"></span> {{ t('simulate_incoming') }}
				</button>
				<button type="button" class="button button-primary tf-btn-primary" @click="composeOpen = true">
					<span class="dashicons dashicons-edit-page"></span> {{ t('compose_email') }}
				</button>
			</div>
		</div>

		<div class="tf-mock-banner">
			<span class="dashicons dashicons-info-outline"></span>
			{{ t('mock_notice') }}
		</div>

		<div class="tf-segmented">
			<button type="button" :class="{ 'tf-segmented__btn--active': 'outbox' === tab }" class="tf-segmented__btn" @click="tab = 'outbox'">
				{{ t('mail_outbox') }} ({{ outbox.length }})
			</button>
			<button type="button" :class="{ 'tf-segmented__btn--active': 'inbox' === tab }" class="tf-segmented__btn" @click="tab = 'inbox'">
				{{ t('mail_inbox') }} ({{ inbox.length }})
			</button>
		</div>

		<tf-error v-if="error" :message="error" @retry="load"></tf-error>
		<tf-skeleton v-else-if="loading" :rows="5"></tf-skeleton>

		<template v-else>
			<tf-empty v-if="!items.length" icon="dashicons-email-alt" :text="'inbox' === tab ? t('mail_no_inbox') : t('mail_no_outbox')"></tf-empty>

			<div class="tf-panel tf-mail-list" v-else>
				<button
					v-for="message in items"
					:key="message.id"
					type="button"
					class="tf-mail"
					:class="{ 'tf-mail--unread': 'inbox' === tab && !parseInt(message.is_read, 10) }"
					@click="open(message)">
					<span class="tf-mail__dot" v-if="'inbox' === tab && !parseInt(message.is_read, 10)"></span>
					<div class="tf-mail__main">
						<div class="tf-mail__row">
							<strong class="tf-mail__addr">{{ 'inbox' === tab ? (message.from_name || message.from_email) : message.to_email }}</strong>
							<time class="tf-mail__time">{{ 'inbox' === tab ? fmtDate(message.received_at, true) : fmtDate(message.sent_at || message.created_at, true) }}</time>
						</div>
						<p class="tf-mail__subject">{{ message.subject }}</p>
						<p class="tf-mail__preview">{{ message.body }}</p>
					</div>
				</button>
			</div>
		</template>

		<tf-modal :title="t('compose_email')" @close="composeOpen = false" v-if="composeOpen">
			<form class="tf-form" @submit.prevent="send">
				<div class="tf-form__row">
					<label class="tf-form__label">{{ t('mail_to') }} *</label>
					<input type="email" v-model="compose.to" class="tf-input" required />
				</div>
				<div class="tf-form__row">
					<label class="tf-form__label">{{ t('mail_subject') }} *</label>
					<input type="text" v-model="compose.subject" class="tf-input" required />
				</div>
				<div class="tf-form__row">
					<label class="tf-form__label">{{ t('mail_body') }}</label>
					<textarea v-model="compose.body" class="tf-input" rows="5"></textarea>
				</div>
				<div class="tf-form__foot">
					<button type="button" class="button" @click="composeOpen = false">{{ t('cancel') }}</button>
					<button type="submit" class="button button-primary tf-btn-primary" :disabled="sending">{{ sending ? t('loading') : t('send') }}</button>
				</div>
			</form>
		</tf-modal>

		<tf-modal :title="t('simulate_incoming')" @close="simulateOpen = false" v-if="simulateOpen">
			<form class="tf-form" @submit.prevent="simulate">
				<div class="tf-form__grid">
					<div class="tf-form__row">
						<label class="tf-form__label">{{ t('mail_from') }} (email) *</label>
						<input type="email" v-model="simulate.from_email" class="tf-input" required />
					</div>
					<div class="tf-form__row">
						<label class="tf-form__label">{{ t('mail_from') }} (name)</label>
						<input type="text" v-model="simulate.from_name" class="tf-input" />
					</div>
				</div>
				<div class="tf-form__row">
					<label class="tf-form__label">{{ t('mail_subject') }} *</label>
					<input type="text" v-model="simulate.subject" class="tf-input" required />
				</div>
				<div class="tf-form__row">
					<label class="tf-form__label">{{ t('mail_body') }}</label>
					<textarea v-model="simulate.body" class="tf-input" rows="5"></textarea>
				</div>
				<div class="tf-form__foot">
					<button type="button" class="button" @click="simulateOpen = false">{{ t('cancel') }}</button>
					<button type="submit" class="button button-primary tf-btn-primary" :disabled="sending">{{ sending ? t('loading') : t('create') }}</button>
				</div>
			</form>
		</tf-modal>

		<tf-modal :title="t('mail_subject')" size="md" @close="viewing = null" v-if="viewing">
			<div class="tf-mail-view">
				<div class="tf-mail-view__row" v-if="'inbox' === viewing.type || viewing.from_email">
					<strong>{{ t('mail_from') }}:</strong>
					<span>{{ viewing.from_name ? viewing.from_name + ' <' + viewing.from_email + '>' : viewing.from_email }}</span>
				</div>
				<div class="tf-mail-view__row" v-if="viewing.to_email">
					<strong>{{ t('mail_to') }}:</strong>
					<span>{{ viewing.to_email }}</span>
				</div>
				<div class="tf-mail-view__row">
					<strong>{{ t('mail_subject') }}:</strong>
					<span>{{ viewing.subject }}</span>
				</div>
				<hr class="tf-hr" />
				<p class="tf-mail-view__body">{{ viewing.body }}</p>
			</div>
		</tf-modal>
	</section>
</script>

<script type="text/x-template" id="tf-tpl-settings">
	<section class="tf-view">
		<div class="tf-view__head">
			<h2 class="tf-view__title">{{ t('settings') }}</h2>
			<button type="button" class="button button-primary tf-btn-primary" v-if="settings" @click="save" :disabled="saving">
				<span class="dashicons dashicons-saved"></span> {{ t('save') }}
			</button>
		</div>

		<tf-error v-if="error" :message="error"></tf-error>
		<tf-skeleton v-else-if="loading" :rows="6"></tf-skeleton>

		<template v-else-if="settings">
			<div class="tf-panel">
				<h3 class="tf-panel__title">{{ t('settings_general') }}</h3>
				<div class="tf-form__grid">
					<div class="tf-form__row">
						<label class="tf-form__label">{{ t('settings_currency') }}</label>
						<input type="text" v-model="settings.currency" class="tf-input" maxlength="8" />
					</div>
					<div class="tf-form__row">
						<label class="tf-form__label">{{ t('settings_per_page') }}</label>
						<input type="number" min="5" max="100" v-model.number="settings.items_per_page" class="tf-input" />
					</div>
				</div>
			</div>

			<div class="tf-panel">
				<h3 class="tf-panel__title">{{ t('settings_notify') }}</h3>
				<div class="tf-switches">
					<label class="tf-switch">
						<input type="checkbox" :checked="settings.notify_on_assignment" @change="toggle('notify_on_assignment')" />
						<span class="tf-switch__track"></span>
						<span>{{ t('settings_notify_assignment') }}</span>
					</label>
					<label class="tf-switch">
						<input type="checkbox" :checked="settings.notify_on_status_change" @change="toggle('notify_on_status_change')" />
						<span class="tf-switch__track"></span>
						<span>{{ t('settings_notify_status') }}</span>
					</label>
					<label class="tf-switch">
						<input type="checkbox" :checked="settings.notify_on_comment" @change="toggle('notify_on_comment')" />
						<span class="tf-switch__track"></span>
						<span>{{ t('settings_notify_comment') }}</span>
					</label>
					<label class="tf-switch">
						<input type="checkbox" :checked="settings.mock_email_on_assignment" @change="toggle('mock_email_on_assignment')" />
						<span class="tf-switch__track"></span>
						<span>{{ t('settings_mock_email') }}</span>
					</label>
				</div>
			</div>

			<div class="tf-panel">
				<h3 class="tf-panel__title">{{ t('settings_roles') }}</h3>
				<p class="tf-muted">{{ t('settings_roles_hint') }}</p>
				<div class="tf-switches">
					<label class="tf-switch" v-for="role in knownRoles" :key="role.key">
						<input type="checkbox" :checked="hasRole(role.key)" @change="toggleRole(role.key)" />
						<span class="tf-switch__track"></span>
						<span>{{ role.label }}</span>
					</label>
				</div>
			</div>

			<div class="tf-panel">
				<h3 class="tf-panel__title">{{ t('settings_data') }}</h3>
				<div class="tf-btn-row">
					<button type="button" class="button" @click="exportData('csv')"><span class="dashicons dashicons-download"></span> {{ t('settings_export_csv') }}</button>
					<button type="button" class="button" @click="exportData('json')"><span class="dashicons dashicons-download"></span> {{ t('settings_export_json') }}</button>
				</div>

				<h4 class="tf-panel__subtitle">{{ t('settings_import') }}</h4>
				<p class="tf-muted">{{ t('settings_import_hint') }}
					<button type="button" class="button-link" @click="downloadTemplate">{{ t('settings_import_download') }}</button>
				</p>
				<div class="tf-import">
					<input type="file" accept=".csv,text/csv" @change="onFilePicked" class="tf-input" />
					<textarea v-model="importText" class="tf-input" rows="5" :placeholder="t('settings_import_hint')"></textarea>
					<button type="button" class="button button-primary tf-btn-primary" :disabled="importing || !importText.trim()" @click="runImport">
						{{ importing ? t('loading') : t('settings_import') }}
					</button>
				</div>
			</div>

			<div class="tf-panel tf-panel--danger">
				<h3 class="tf-panel__title">{{ t('settings_uninstall') }}</h3>
				<p class="tf-muted">{{ t('settings_uninstall_hint') }}</p>
				<label class="tf-switch tf-switch--danger">
					<input type="checkbox" :checked="settings.delete_data_on_uninstall" @change="toggle('delete_data_on_uninstall')" />
					<span class="tf-switch__track"></span>
					<span>{{ t('settings_delete_on_uninstall') }}</span>
				</label>
			</div>

			<div class="tf-panel">
				<h3 class="tf-panel__title">{{ t('settings_seed') }}</h3>
				<p class="tf-muted">{{ t('settings_seed_hint') }}</p>
				<button type="button" class="button" @click="seed"><span class="dashicons dashicons-database-view"></span> {{ t('settings_seed') }}</button>
			</div>

			<div class="tf-panel" v-if="system">
				<h3 class="tf-panel__title">{{ t('settings_system') }}</h3>
				<table class="tf-sysinfo">
					<tbody>
						<tr><th>TaskFlow</th><td>{{ system.plugin_version }}</td></tr>
						<tr><th>WordPress</th><td>{{ system.wordpress }}</td></tr>
						<tr><th>PHP</th><td>{{ system.php }}</td></tr>
						<tr><th>Database</th><td>{{ system.database }}</td></tr>
						<tr><th>Locale</th><td>{{ system.locale }}</td></tr>
						<tr><th>Theme</th><td>{{ system.active_theme }}</td></tr>
						<tr><th>{{ t('settings_uninstall') }}</th><td>{{ system.uninstall_mode }}</td></tr>
						<tr v-for="(info, name) in system.tables" :key="name">
							<th>{{ name }}</th>
							<td><code class="tf-code">{{ info.table }}</code> — {{ info.rows }}</td>
						</tr>
					</tbody>
				</table>
			</div>
		</template>
	</section>
</script>
