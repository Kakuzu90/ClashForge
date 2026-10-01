import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Settings\EmailChangeController::update
* @see app/Http/Controllers/Settings/EmailChangeController.php:29
* @route '/settings/security/email'
*/
export const update = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/settings/security/email',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Settings\EmailChangeController::update
* @see app/Http/Controllers/Settings/EmailChangeController.php:29
* @route '/settings/security/email'
*/
update.url = (options?: RouteQueryOptions) => {
    return update.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\EmailChangeController::update
* @see app/Http/Controllers/Settings/EmailChangeController.php:29
* @route '/settings/security/email'
*/
update.put = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Settings\EmailChangeController::resend
* @see app/Http/Controllers/Settings/EmailChangeController.php:39
* @route '/settings/security/email/resend'
*/
export const resend = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resend.url(options),
    method: 'post',
})

resend.definition = {
    methods: ["post"],
    url: '/settings/security/email/resend',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Settings\EmailChangeController::resend
* @see app/Http/Controllers/Settings/EmailChangeController.php:39
* @route '/settings/security/email/resend'
*/
resend.url = (options?: RouteQueryOptions) => {
    return resend.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\EmailChangeController::resend
* @see app/Http/Controllers/Settings/EmailChangeController.php:39
* @route '/settings/security/email/resend'
*/
resend.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resend.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Settings\EmailChangeController::destroy
* @see app/Http/Controllers/Settings/EmailChangeController.php:53
* @route '/settings/security/email'
*/
export const destroy = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/settings/security/email',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Settings\EmailChangeController::destroy
* @see app/Http/Controllers/Settings/EmailChangeController.php:53
* @route '/settings/security/email'
*/
destroy.url = (options?: RouteQueryOptions) => {
    return destroy.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\EmailChangeController::destroy
* @see app/Http/Controllers/Settings/EmailChangeController.php:53
* @route '/settings/security/email'
*/
destroy.delete = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(options),
    method: 'delete',
})

const email = {
    update: Object.assign(update, update),
    resend: Object.assign(resend, resend),
    destroy: Object.assign(destroy, destroy),
}

export default email