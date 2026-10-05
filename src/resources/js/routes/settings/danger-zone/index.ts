import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Settings\AccountDeletionController::edit
* @see app/Http/Controllers/Settings/AccountDeletionController.php:20
* @route '/settings/danger-zone'
*/
export const edit = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/settings/danger-zone',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Settings\AccountDeletionController::edit
* @see app/Http/Controllers/Settings/AccountDeletionController.php:20
* @route '/settings/danger-zone'
*/
edit.url = (options?: RouteQueryOptions) => {
    return edit.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\AccountDeletionController::edit
* @see app/Http/Controllers/Settings/AccountDeletionController.php:20
* @route '/settings/danger-zone'
*/
edit.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Settings\AccountDeletionController::edit
* @see app/Http/Controllers/Settings/AccountDeletionController.php:20
* @route '/settings/danger-zone'
*/
edit.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Settings\AccountDeletionController::destroy
* @see app/Http/Controllers/Settings/AccountDeletionController.php:32
* @route '/settings/danger-zone'
*/
export const destroy = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/settings/danger-zone',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Settings\AccountDeletionController::destroy
* @see app/Http/Controllers/Settings/AccountDeletionController.php:32
* @route '/settings/danger-zone'
*/
destroy.url = (options?: RouteQueryOptions) => {
    return destroy.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\AccountDeletionController::destroy
* @see app/Http/Controllers/Settings/AccountDeletionController.php:32
* @route '/settings/danger-zone'
*/
destroy.delete = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(options),
    method: 'delete',
})

const dangerZone = {
    edit: Object.assign(edit, edit),
    destroy: Object.assign(destroy, destroy),
}

export default dangerZone