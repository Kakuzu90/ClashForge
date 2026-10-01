import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Settings\EmailPreferenceController::edit
* @see app/Http/Controllers/Settings/EmailPreferenceController.php:17
* @route '/settings/notifications'
*/
export const edit = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/settings/notifications',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Settings\EmailPreferenceController::edit
* @see app/Http/Controllers/Settings/EmailPreferenceController.php:17
* @route '/settings/notifications'
*/
edit.url = (options?: RouteQueryOptions) => {
    return edit.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\EmailPreferenceController::edit
* @see app/Http/Controllers/Settings/EmailPreferenceController.php:17
* @route '/settings/notifications'
*/
edit.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Settings\EmailPreferenceController::edit
* @see app/Http/Controllers/Settings/EmailPreferenceController.php:17
* @route '/settings/notifications'
*/
edit.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Settings\EmailPreferenceController::update
* @see app/Http/Controllers/Settings/EmailPreferenceController.php:22
* @route '/settings/notifications'
*/
export const update = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/settings/notifications',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Settings\EmailPreferenceController::update
* @see app/Http/Controllers/Settings/EmailPreferenceController.php:22
* @route '/settings/notifications'
*/
update.url = (options?: RouteQueryOptions) => {
    return update.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\EmailPreferenceController::update
* @see app/Http/Controllers/Settings/EmailPreferenceController.php:22
* @route '/settings/notifications'
*/
update.patch = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(options),
    method: 'patch',
})

const notifications = {
    edit: Object.assign(edit, edit),
    update: Object.assign(update, update),
}

export default notifications