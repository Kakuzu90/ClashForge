import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Settings\SecurityController::edit
* @see app/Http/Controllers/Settings/SecurityController.php:22
* @route '/settings/security'
*/
export const edit = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/settings/security',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Settings\SecurityController::edit
* @see app/Http/Controllers/Settings/SecurityController.php:22
* @route '/settings/security'
*/
edit.url = (options?: RouteQueryOptions) => {
    return edit.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\SecurityController::edit
* @see app/Http/Controllers/Settings/SecurityController.php:22
* @route '/settings/security'
*/
edit.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Settings\SecurityController::edit
* @see app/Http/Controllers/Settings/SecurityController.php:22
* @route '/settings/security'
*/
edit.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Settings\SecurityController::updatePassword
* @see app/Http/Controllers/Settings/SecurityController.php:35
* @route '/settings/security/password'
*/
export const updatePassword = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: updatePassword.url(options),
    method: 'put',
})

updatePassword.definition = {
    methods: ["put"],
    url: '/settings/security/password',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Settings\SecurityController::updatePassword
* @see app/Http/Controllers/Settings/SecurityController.php:35
* @route '/settings/security/password'
*/
updatePassword.url = (options?: RouteQueryOptions) => {
    return updatePassword.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\SecurityController::updatePassword
* @see app/Http/Controllers/Settings/SecurityController.php:35
* @route '/settings/security/password'
*/
updatePassword.put = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: updatePassword.url(options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Settings\SecurityController::destroyOtherSessions
* @see app/Http/Controllers/Settings/SecurityController.php:59
* @route '/settings/security/sessions'
*/
export const destroyOtherSessions = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroyOtherSessions.url(options),
    method: 'delete',
})

destroyOtherSessions.definition = {
    methods: ["delete"],
    url: '/settings/security/sessions',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Settings\SecurityController::destroyOtherSessions
* @see app/Http/Controllers/Settings/SecurityController.php:59
* @route '/settings/security/sessions'
*/
destroyOtherSessions.url = (options?: RouteQueryOptions) => {
    return destroyOtherSessions.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\SecurityController::destroyOtherSessions
* @see app/Http/Controllers/Settings/SecurityController.php:59
* @route '/settings/security/sessions'
*/
destroyOtherSessions.delete = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroyOtherSessions.url(options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Settings\SecurityController::destroySession
* @see app/Http/Controllers/Settings/SecurityController.php:52
* @route '/settings/security/sessions/{key}'
*/
export const destroySession = (args: { key: string | number } | [key: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroySession.url(args, options),
    method: 'delete',
})

destroySession.definition = {
    methods: ["delete"],
    url: '/settings/security/sessions/{key}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Settings\SecurityController::destroySession
* @see app/Http/Controllers/Settings/SecurityController.php:52
* @route '/settings/security/sessions/{key}'
*/
destroySession.url = (args: { key: string | number } | [key: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { key: args }
    }

    if (Array.isArray(args)) {
        args = {
            key: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        key: args.key,
    }

    return destroySession.definition.url
            .replace('{key}', parsedArgs.key.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\SecurityController::destroySession
* @see app/Http/Controllers/Settings/SecurityController.php:52
* @route '/settings/security/sessions/{key}'
*/
destroySession.delete = (args: { key: string | number } | [key: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroySession.url(args, options),
    method: 'delete',
})

const SecurityController = { edit, updatePassword, destroyOtherSessions, destroySession }

export default SecurityController