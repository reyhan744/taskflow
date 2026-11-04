<?php
/**
 * Vue.js application templates (x-template blocks), part 1: shell, bell,
 * timer, dashboard, tasks, Kanban, task form, task drawer.
 *
 * The templates are static HTML consumed by the no-build Vue 3 app; they are
 * loaded only on the TaskFlow admin screen.
 *
 * @package TaskFlow
 */

defined( 'ABSPATH' ) || exit;
?>
<script type="text/x-template" id="tf-tpl-app">
	<div class="tf-shell" :class="{ 'tf-rtl': state.boot.isRtl }">
		<header class="tf-topbar">
			<div class="tf-topbar__brand">
				<span class="dashicons dashicons-yes-alt tf-logo" aria-hidden="true"></span>
				<div class="tf-topbar__titles">
					<h1 class="tf-topbar__title">{{ t('app_title') }}</h1>
					<p class="tf-topbar__subtitle">{{ t('app_subtitle') }}</p>
				</div>
			</div>
			<div class="tf-topbar__actions">
				<tf-timer-chip></tf-timer-chip>
				<tf-bell></tf-bell>
				<span class="tf-userchip">
					<img class="tf-userchip__avatar" :src="state.boot.user.avatar" alt="" />
					<span class="tf-userchip__name">{{ state.boot.user.name }}</span>
				</span>
			</div>
		</header>

		<nav class="tf-tabs">
			<button
				v-for="tab in tabs"
				:key="tab.key"
				type="button"
				class="tf-tab"
				:class="{ 'tf-tab--active': route === tab.key }"
				@click="go(tab.key)">
				<span class="dashicons" :class="tab.icon" aria-hidden="true"></span>
				<span>{{ tab.label }}</span>
			</button>
		</nav>

		<main class="tf-main">
			<keep-alive>
				<component :is="currentView"></component>
			</keep-alive>
		</main>

		<tf-task-form></tf-task-form>
		<tf-task-drawer></tf-task-drawer>
		<tf-toasts></tf-toasts>
		<tf-confirm></tf-confirm>
	</div>
</script>

<script type="text/x-template" id="tf-tpl-bell">
	<div class="tf-bell">
		<button type="button" class="tf-icon-btn tf-bell__btn" @click="toggle" :aria-label="t('notifications')">
			<span class="dashicons dashicons-bell"></span>
			<span class="tf-bell__badge" v-if="state.unread > 0">{{ state.unread > 99 ? '99+' : state.unread }}</span>
		</button>
		<transition name="tf-fade">
			<div class="tf-bell__panel" v-if="state.bellOpen">
				<div class="tf-bell__head">
					<strong>{{ t('notifications') }}</strong>
					<button type="button" class="button-link" @click="markAll" v-if="state.unread > 0">{{ t('mark_all_read') }}</button>
				</div>
				<tf-empty v-if="!state.notifications.length" icon="dashicons-bell" :text="t('no_notifications')"></tf-empty>
				<ul class="tf-bell__list" v-else>
					<li v-for="item in state.notifications" :key="item.id"
						class="tf-bell__item" :class="{ 'tf-bell__item--unread': !parseInt(item.is_read, 10) }"
						@click="open(item)">
						<span class="tf-bell__dot" aria-hidden="true"></span>
						<div class="tf-bell__body">
							<p class="tf-bell__msg">{{ item.message }}</p>
							<time class="tf-bell__time">{{ ago(item.created_at) }}</time>
						</div>
					</li>
				</ul>
			</div>
		</transition>
	</div>
</script>

<script type="text/x-template" id="tf-tpl-timer">
	<div class="tf-timer" v-if="state.activeTimer">
		<span class="tf-timer__pulse" aria-hidden="true"></span>
		<span class="tf-timer__time">{{ label }}</span>
		<button type="button" class="tf-timer__stop" @click="stop" :aria-label="t('stop_timer')">
			<span class="dashicons dashicons-controls-pause"></span>
		</button>
	</div>
</script>

<script type="text/x-template" id="tf-tpl-dashboard">
	<section class="tf-view">
		<div class="tf-view__head">
			<h2 class="tf-view__title">{{ t('dashboard') }}</h2>
		</div>

		<tf-error v-if="error" :message="error" @retry="load"></tf-error>
		<tf-skeleton v-else-if="loading" :rows="6"></tf-skeleton>

		<template v-else>
			<div class="tf-stats">
				<div v-for="card in cards" :key="card.key" class="tf-stat" :class="'tf-stat--' + card.tone">
					<span class="dashicons tf-stat__icon" :class="card.icon" aria-hidden="true"></span>
					<div class="tf-stat__meta">
						<span class="tf-stat__value">{{ card.value }}</span>
						<span class="tf-stat__label">{{ card.label }}</span>
					</div>
				</div>
			</div>

			<div class="tf-grid tf-grid--2">
				<div class="tf-panel">
					<h3 class="tf-panel__title">{{ t('dash_by_priority') }}</h3>
					<div class="tf-pri-rows">
						<div v-for="row in priorityRows" :key="row.key" class="tf-pri-row">
							<span class="tf-pri-row__label">{{ row.label }}</span>
							<div class="tf-pri-row__track">
								<div class="tf-pri-row__fill" :class="'tf-pri-row__fill--' + row.key" :style="{ width: row.pct + '%' }"></div>
							</div>
							<span class="tf-pri-row__count">{{ row.count }}</span>
						</div>
					</div>
				</div>

				<div class="tf-panel">
					<h3 class="tf-panel__title">{{ t('dash_completion') }}</h3>
					<div class="tf-completion">
						<div class="tf-completion__ring" :style="{ '--tf-pct': stats.completion_rate }">
							<span class="tf-completion__value">{{ stats.completion_rate }}%</span>
						</div>
						<ul class="tf-completion__list">
							<li><strong>{{ stats.counts.my_open }}</strong> {{ t('dash_my_open') }}</li>
							<li><strong>{{ fmtDuration(stats.time_spent) }}</strong> {{ t('dash_time') }}</li>
							<li><strong>{{ stats.counts.due_today }}</strong> {{ t('dash_due_today') }}</li>
						</ul>
					</div>
				</div>
			</div>

			<div class="tf-panel" v-if="stats.by_project.length">
				<h3 class="tf-panel__title">{{ t('dash_by_project') }}</h3>
				<div class="tf-proj-rows">
					<div v-for="proj in stats.by_project" :key="proj.id" class="tf-proj-row">
						<span class="tf-proj-row__color" :style="{ background: proj.color }"></span>
						<span class="tf-proj-row__name">{{ proj.name }}</span>
						<div class="tf-proj-row__track">
							<div class="tf-proj-row__fill" :style="{ width: (proj.total ? Math.round(100 * proj.completed / proj.total) : 0) + '%' }"></div>
						</div>
						<span class="tf-proj-row__count">{{ proj.completed }}/{{ proj.total }}</span>
					</div>
				</div>
			</div>

			<div class="tf-panel">
				<h3 class="tf-panel__title">{{ t('dash_recent') }}</h3>
				<tf-empty v-if="!stats.recent_activity.length" icon="dashicons-backup" :text="t('no_activity')"></tf-empty>
				<ul class="tf-feed" v-else>
					<li v-for="event in stats.recent_activity" :key="event.id"
						class="tf-feed__item" :class="{ 'tf-feed__item--link': event.task_id }"
						@click="openTask(event.task_id)">
						<span class="tf-feed__icon"><span class="dashicons dashicons-marker" aria-hidden="true"></span></span>
						<div class="tf-feed__body">
							<p class="tf-feed__msg">{{ event.message }}</p>
							<span class="tf-feed__meta">
								<span v-if="event.user">{{ event.user.name }}</span>
								<time>{{ ago(event.created_at) }}</time>
							</span>
						</div>
					</li>
				</ul>
			</div>
		</template>
	</section>
</script>

<script type="text/x-template" id="tf-tpl-tasks">
	<section class="tf-view">
		<div class="tf-view__head">
			<h2 class="tf-view__title">{{ t('tasks') }}</h2>
			<button type="button" class="button button-primary tf-btn-primary" v-if="canEdit" @click="newTask">
				<span class="dashicons dashicons-plus-alt2"></span> {{ t('new_task') }}
			</button>
		</div>

		<div class="tf-toolbar">
			<div class="tf-toolbar__search">
				<span class="dashicons dashicons-search"></span>
				<input type="search" v-model="filters.search" :placeholder="t('search')" />
			</div>
			<select v-model="filters.status" @change="load(1)" class="tf-select">
				<option value="">{{ t('filter_status') }}: {{ t('all') }}</option>
				<option value="new">{{ t('status_new') }}</option>
				<option value="in_progress">{{ t('status_in_progress') }}</option>
				<option value="completed">{{ t('status_completed') }}</option>
			</select>
			<select v-model="filters.priority" @change="load(1)" class="tf-select">
				<option value="">{{ t('filter_priority') }}: {{ t('all') }}</option>
				<option value="low">{{ t('priority_low') }}</option>
				<option value="medium">{{ t('priority_medium') }}</option>
				<option value="high">{{ t('priority_high') }}</option>
				<option value="urgent">{{ t('priority_urgent') }}</option>
			</select>
			<select v-model="filters.assignee_id" @change="load(1)" class="tf-select">
				<option value="">{{ t('filter_assignee') }}: {{ t('all') }}</option>
				<option value="me">{{ t('my_tasks') }}</option>
				<option value="none">{{ t('task_unassigned') }}</option>
				<option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
			</select>
			<select v-model="filters.project_id" @change="load(1)" class="tf-select">
				<option value="">{{ t('filter_project') }}: {{ t('all') }}</option>
				<option value="none">{{ t('no_project') }}</option>
				<option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
			</select>
			<select v-model="filters.tag_id" @change="load(1)" class="tf-select">
				<option value="">{{ t('filter_tag') }}: {{ t('all') }}</option>
				<option v-for="tag in tags" :key="tag.id" :value="tag.id">{{ tag.name }}</option>
			</select>
			<label class="tf-check">
				<input type="checkbox" v-model="filters.overdue" @change="load(1)" />
				<span>{{ t('filter_overdue') }}</span>
			</label>
			<select v-model="filters.orderby" @change="load(1)" class="tf-select">
				<option value="created_at">{{ t('filter_sort') }}: {{ t('sort_created') }}</option>
				<option value="updated_at">{{ t('filter_sort') }}: {{ t('sort_updated') }}</option>
				<option value="due_date">{{ t('filter_sort') }}: {{ t('sort_due') }}</option>
				<option value="priority">{{ t('filter_sort') }}: {{ t('sort_priority') }}</option>
				<option value="title">{{ t('filter_sort') }}: {{ t('sort_title') }}</option>
			</select>
			<select v-model="filters.order" @change="load(1)" class="tf-select">
				<option value="DESC">{{ t('sort_desc') }}</option>
				<option value="ASC">{{ t('sort_asc') }}</option>
			</select>
			<button type="button" class="button" v-if="hasFilters" @click="resetFilters">{{ t('reset') }}</button>
		</div>

		<tf-error v-if="error" :message="error" @retry="load()"></tf-error>
		<tf-skeleton v-else-if="loading" :rows="6"></tf-skeleton>

		<template v-else>
			<tf-empty v-if="!items.length" icon="dashicons-list-view" :text="t('no_tasks')">
				<button type="button" class="button button-primary tf-btn-primary" v-if="canEdit" @click="newTask">{{ t('new_task') }}</button>
			</tf-empty>

			<div class="tf-table-wrap" v-else>
				<table class="tf-table">
					<thead>
						<tr>
							<th class="tf-col-title">{{ t('task_title') }}</th>
							<th>{{ t('task_status') }}</th>
							<th>{{ t('task_priority') }}</th>
							<th>{{ t('task_assignee') }}</th>
							<th>{{ t('task_project') }}</th>
							<th>{{ t('task_due_date') }}</th>
							<th class="tf-col-actions"></th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="task in items" :key="task.id" @click="openTask(task)" class="tf-row">
							<td class="tf-col-title">
								<span class="tf-row__title">{{ task.title }}</span>
								<span class="tf-chips" v-if="task.tags.length">
									<span v-for="tag in task.tags" :key="tag.id" class="tf-chip" :style="{ '--tag-color': tag.color }">{{ tag.name }}</span>
								</span>
							</td>
							<td @click.stop>
								<select class="tf-inline-status" v-model="task.status" @change="quickStatus(task)">
									<option value="new">{{ t('status_new') }}</option>
									<option value="in_progress">{{ t('status_in_progress') }}</option>
									<option value="completed">{{ t('status_completed') }}</option>
								</select>
							</td>
							<td><tf-badge-priority :priority="task.priority"></tf-badge-priority></td>
							<td><tf-user :user="task.assignee"></tf-user></td>
							<td>
								<span class="tf-proj" v-if="task.project">
									<span class="tf-proj__color" :style="{ background: task.project.color }"></span>{{ task.project.name }}
								</span>
								<span v-else class="tf-muted">—</span>
							</td>
							<td>
								<span class="tf-due" :class="{ 'tf-due--overdue': task.overdue }">
									{{ fmtDate(task.due_date) }}
									<em v-if="task.overdue">{{ t('overdue') }}</em>
								</span>
							</td>
							<td class="tf-col-actions" @click.stop>
								<button type="button" class="tf-icon-btn" v-if="task.can_edit" @click="editTask(task)" :aria-label="t('edit')">
									<span class="dashicons dashicons-edit"></span>
								</button>
								<button type="button" class="tf-icon-btn tf-icon-btn--danger" v-if="task.can_delete" @click="deleteTask(task)" :aria-label="t('delete')">
									<span class="dashicons dashicons-trash"></span>
								</button>
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			<tf-pagination
				:page="page" :total-pages="totalPages" :total="total" :per-page="perPage"
				@go="goPage"></tf-pagination>
		</template>
	</section>
</script>

<script type="text/x-template" id="tf-tpl-kanban">
	<section class="tf-view">
		<div class="tf-view__head">
			<h2 class="tf-view__title">{{ t('kanban') }}</h2>
			<button type="button" class="button button-primary tf-btn-primary" @click="newTask">
				<span class="dashicons dashicons-plus-alt2"></span> {{ t('new_task') }}
			</button>
		</div>

		<tf-error v-if="error" :message="error" @retry="load"></tf-error>
		<tf-skeleton v-else-if="loading" :rows="4"></tf-skeleton>

		<div class="tf-board" v-else>
			<div
				v-for="col in columns"
				:key="col.status"
				class="tf-board__col"
				:class="{ 'tf-board__col--over': dragOver === col.status }"
				@dragover.prevent="onDragOver(col.status, $event)"
				@dragleave="onDragLeave"
				@drop.prevent="onDrop(col.status)">
				<div class="tf-board__head" :class="'tf-board__head--' + col.status">
					<span>{{ col.label }}</span>
					<span class="tf-board__count">{{ col.cards.length }}</span>
				</div>

				<div class="tf-board__body">
					<tf-empty v-if="!col.cards.length" icon="dashicons-minus" :text="t('no_tasks')"></tf-empty>
					<article
						v-for="card in col.cards"
						:key="card.id"
						class="tf-card"
						draggable="true"
						@dragstart="onDragStart(card, $event)"
						@click="openTask(card)">
						<div class="tf-card__top">
							<tf-badge-priority :priority="card.priority"></tf-badge-priority>
							<time class="tf-card__due" :class="{ 'tf-card__due--overdue': card.overdue }" v-if="card.due_date">{{ fmtDate(card.due_date) }}</time>
						</div>
						<h4 class="tf-card__title">{{ card.title }}</h4>
						<div class="tf-card__tags" v-if="card.tags.length">
							<span v-for="tag in card.tags" :key="tag.id" class="tf-chip" :style="{ '--tag-color': tag.color }">{{ tag.name }}</span>
						</div>
						<div class="tf-card__foot">
							<img v-if="card.assignee" class="tf-card__avatar" :src="card.assignee.avatar" :alt="card.assignee.name" :title="card.assignee.name" />
							<span class="tf-card__project" v-if="card.project">
								<span class="tf-proj__color" :style="{ background: card.project.color }"></span>{{ card.project.name }}
							</span>
							<select class="tf-card__move" @click.stop @change="move(card.id, $event.target.value); $event.target.value = ''" :aria-label="t('move_to')">
								<option value="">{{ t('move_to') }}…</option>
								<option v-for="c in columns" :key="c.status" :value="c.status" :disabled="c.status === card.status">{{ c.label }}</option>
							</select>
						</div>
					</article>
				</div>
			</div>
		</div>
	</section>
</script>

<script type="text/x-template" id="tf-tpl-task-form">
	<tf-modal :title="title" size="lg" @close="close" v-if="open">
		<form class="tf-form" @submit.prevent="save">
			<div class="tf-form__row">
				<label class="tf-form__label">{{ t('task_title') }} *</label>
				<input type="text" v-model="form.title" class="tf-input" :class="{ 'tf-input--error': errors.title }" />
				<p class="tf-form__error" v-if="errors.title">{{ errors.title }}</p>
			</div>

			<div class="tf-form__row">
				<label class="tf-form__label">{{ t('task_description') }}</label>
				<textarea v-model="form.description" class="tf-input" rows="4"></textarea>
			</div>

			<div class="tf-form__grid">
				<div class="tf-form__row">
					<label class="tf-form__label">{{ t('task_status') }}</label>
					<select v-model="form.status" class="tf-input">
						<option value="new">{{ t('status_new') }}</option>
						<option value="in_progress">{{ t('status_in_progress') }}</option>
						<option value="completed">{{ t('status_completed') }}</option>
					</select>
				</div>
				<div class="tf-form__row">
					<label class="tf-form__label">{{ t('task_priority') }}</label>
					<select v-model="form.priority" class="tf-input">
						<option value="low">{{ t('priority_low') }}</option>
						<option value="medium">{{ t('priority_medium') }}</option>
						<option value="high">{{ t('priority_high') }}</option>
						<option value="urgent">{{ t('priority_urgent') }}</option>
					</select>
				</div>
				<div class="tf-form__row">
					<label class="tf-form__label">{{ t('task_due_date') }}</label>
					<input type="datetime-local" v-model="form.due_date" class="tf-input" />
				</div>
				<div class="tf-form__row">
					<label class="tf-form__label">{{ t('task_project') }}</label>
					<select v-model="form.project_id" class="tf-input">
						<option value="0">{{ t('no_project') }}</option>
						<option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
					</select>
				</div>
				<div class="tf-form__row">
					<label class="tf-form__label">{{ t('task_assignee') }}</label>
					<select v-model="form.assignee_id" class="tf-input">
						<option value="0">{{ t('task_unassigned') }}</option>
						<option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
					</select>
				</div>
			</div>

			<div class="tf-form__row" v-if="tags.length">
				<label class="tf-form__label">{{ t('task_tags') }}</label>
				<div class="tf-tag-picker">
					<button
						v-for="tag in tags" :key="tag.id"
						type="button"
						class="tf-chip tf-chip--btn"
						:class="{ 'tf-chip--active': form.tag_ids.indexOf(tag.id) !== -1 }"
						:style="{ '--tag-color': tag.color }"
						@click="toggleTag(tag.id)">{{ tag.name }}</button>
				</div>
			</div>

			<div class="tf-form__foot">
				<button type="button" class="button" @click="close">{{ t('cancel') }}</button>
				<button type="submit" class="button button-primary tf-btn-primary" :disabled="saving">
					{{ saving ? t('loading') : (isEdit ? t('update') : t('create')) }}
				</button>
			</div>
		</form>
	</tf-modal>
</script>

<script type="text/x-template" id="tf-tpl-drawer">
	<transition name="tf-slide">
		<div class="tf-drawer-wrap" v-if="isOpen">
			<div class="tf-drawer-backdrop" @click="close"></div>
			<aside class="tf-drawer" role="dialog" aria-modal="true">
				<div class="tf-drawer__loading" v-if="loading">
					<tf-skeleton :rows="6"></tf-skeleton>
				</div>
				<tf-error v-else-if="error" :message="error" @retry="load"></tf-error>

				<template v-else-if="task">
					<header class="tf-drawer__head">
						<div>
							<div class="tf-drawer__badges">
								<tf-badge-status :status="task.status"></tf-badge-status>
								<tf-badge-priority :priority="task.priority"></tf-badge-priority>
								<span class="tf-badge tf-badge--overdue" v-if="task.overdue">{{ t('overdue') }}</span>
							</div>
							<h2 class="tf-drawer__title">{{ task.title }}</h2>
						</div>
						<button type="button" class="tf-icon-btn" @click="close" :aria-label="t('close')">
							<span class="dashicons dashicons-no-alt"></span>
						</button>
					</header>

					<div class="tf-drawer__quick">
						<div class="tf-quick-field" v-if="task.can_change_status">
							<label>{{ t('task_status') }}</label>
							<select class="tf-input" :value="task.status" @change="setStatus($event.target.value)">
								<option value="new">{{ t('status_new') }}</option>
								<option value="in_progress">{{ t('status_in_progress') }}</option>
								<option value="completed">{{ t('status_completed') }}</option>
							</select>
						</div>
						<div class="tf-quick-field" v-if="task.can_edit">
							<label>{{ t('task_assignee') }}</label>
							<select class="tf-input" :value="task.assignee_id || ''" @change="patchTask({ assignee_id: parseInt($event.target.value, 10) || 0 })">
								<option value="">{{ t('task_unassigned') }}</option>
								<option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
							</select>
						</div>
						<div class="tf-drawer__actions">
							<button type="button" class="button" v-if="task.can_edit" @click="editTask">
								<span class="dashicons dashicons-edit"></span> {{ t('edit') }}
							</button>
							<button type="button" class="button tf-btn-danger" v-if="task.can_delete" @click="deleteTask">
								<span class="dashicons dashicons-trash"></span> {{ t('delete') }}
							</button>
						</div>
					</div>

					<dl class="tf-meta">
						<div class="tf-meta__row" v-if="task.project">
							<dt>{{ t('task_project') }}</dt>
							<dd><span class="tf-proj"><span class="tf-proj__color" :style="{ background: task.project.color }"></span>{{ task.project.name }}</span></dd>
						</div>
						<div class="tf-meta__row" v-if="task.assignee">
							<dt>{{ t('task_assignee') }}</dt>
							<dd><tf-user :user="task.assignee"></tf-user></dd>
						</div>
						<div class="tf-meta__row" v-if="task.creator">
							<dt>{{ t('created_by') }}</dt>
							<dd><tf-user :user="task.creator"></tf-user></dd>
						</div>
						<div class="tf-meta__row" v-if="task.due_date">
							<dt>{{ t('task_due_date') }}</dt>
							<dd><span class="tf-due" :class="{ 'tf-due--overdue': task.overdue }">{{ fmtDate(task.due_date, true) }}</span></dd>
						</div>
						<div class="tf-meta__row">
							<dt>{{ t('time_spent') }}</dt>
							<dd>
								<span>{{ fmtDuration(task.time_spent) }}</span>
								<button type="button" class="button-link tf-timer-toggle" @click="toggleTimer">
									<span class="dashicons" :class="timerOnThis ? 'dashicons-controls-pause' : 'dashicons-clock'"></span>
									{{ timerOnThis ? t('stop_timer') : t('start_timer') }}
								</button>
							</dd>
						</div>
						<div class="tf-meta__row" v-if="task.completed_at">
							<dt>{{ t('completed_on') }}</dt>
							<dd>{{ fmtDate(task.completed_at, true) }}</dd>
						</div>
					</dl>

					<div class="tf-drawer__desc" v-if="task.description">{{ task.description }}</div>

					<div class="tf-drawer__tags">
						<span v-for="tag in task.tags" :key="tag.id" class="tf-chip tf-chip--btn tf-chip--active" :style="{ '--tag-color': tag.color }" @click="task.can_edit && toggleTag(tag.id)">{{ tag.name }}</span>
						<span class="tf-tag-add" v-if="task.can_edit">
							<input type="text" class="tf-input tf-input--sm" v-model="newTagName" :placeholder="t('tag_name')" @keyup.enter="addTag" />
							<button type="button" class="button" @click="addTag">+</button>
						</span>
					</div>

					<section class="tf-drawer__section">
						<h3>{{ t('comments') }} <span class="tf-count-pill">{{ comments.length }}</span></h3>
						<ul class="tf-comments" v-if="comments.length">
							<li v-for="comment in comments" :key="comment.id" class="tf-comment">
								<img class="tf-comment__avatar" v-if="comment.user" :src="comment.user.avatar" alt="" />
								<div class="tf-comment__bubble">
									<div class="tf-comment__head">
										<strong v-if="comment.user">{{ comment.user.name }}</strong>
										<time>{{ ago(comment.created_at) }}</time>
									</div>
									<p class="tf-comment__text">{{ comment.content }}</p>
								</div>
							</li>
						</ul>
						<p class="tf-muted" v-else>{{ t('no_activity') }}</p>
						<div class="tf-comment-form">
							<textarea class="tf-input" rows="2" v-model="commentText" :placeholder="t('add_comment')"></textarea>
							<button type="button" class="button button-primary tf-btn-primary" :disabled="sendingComment || !commentText.trim()" @click="addComment">{{ t('send') }}</button>
						</div>
					</section>

					<section class="tf-drawer__section" v-if="timeLogs.length">
						<h3>{{ t('time_spent') }}</h3>
						<ul class="tf-time-logs">
							<li v-for="log in timeLogs" :key="log.id">
								<span class="dashicons dashicons-clock"></span>
								<strong>{{ fmtDuration(log.duration) }}</strong>
								<span class="tf-muted">— {{ fmtDate(log.started_at, true) }}</span>
							</li>
						</ul>
					</section>

					<section class="tf-drawer__section">
						<h3>{{ t('history') }}</h3>
						<ul class="tf-timeline" v-if="activity.length">
							<li v-for="event in activity" :key="event.id" class="tf-timeline__item">
								<span class="tf-timeline__icon"><span class="dashicons" :class="activityIcon(event.type)"></span></span>
								<div class="tf-timeline__body">
									<p>{{ event.message }}</p>
									<span class="tf-timeline__meta">
										<span v-if="event.user">{{ event.user.name }}</span>
										<time>{{ fmtDate(event.created_at, true) }}</time>
									</span>
								</div>
							</li>
						</ul>
						<p class="tf-muted" v-else>{{ t('no_activity') }}</p>
					</section>
				</template>
			</aside>
		</div>
	</transition>
</script>
