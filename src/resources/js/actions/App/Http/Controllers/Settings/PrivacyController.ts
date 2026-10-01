import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Settings\PrivacyController::edit
* @see app/Http/Controllers/Settings/PrivacyController.php:23
* @route '/settings/privacy'
*/
export const edit = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/settings/privacy',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Settings\PrivacyController::edit
* @see app/Http/Controllers/Settings/PrivacyController.php:23
* @route '/settings/privacy'
*/
edit.url = (options?: RouteQueryOptions) => {
    return edit.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\PrivacyController::edit
* @see app/Http/Controllers/Settings/PrivacyController.php:23
* @route '/settings/privacy'
*/
edit.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Settings\PrivacyController::edit
* @see app/Http/Controllers/Settings/PrivacyController.php:23
* @route '/settings/privacy'
*/
edit.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Settings\PrivacyController::update
* @see app/Http/Controllers/Settings/PrivacyController.php:36
* @route '/settings/privacy'
*/
export const update = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/settings/privacy',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Settings\PrivacyController::update
* @see app/Http/Controllers/Settings/PrivacyController.php:36
* @route '/settings/privacy'
*/
update.url = (options?: RouteQueryOptions) => {
    return update.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\PrivacyController::update
* @see app/Http/Controllers/Settings/PrivacyController.php:36
* @route '/settings/privacy'
*/
update.patch = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(options),
    method: 'patch',
})

const PrivacyController = { edit, update }

export default PrivacyController