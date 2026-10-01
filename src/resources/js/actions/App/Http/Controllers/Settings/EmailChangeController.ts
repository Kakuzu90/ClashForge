import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Settings\EmailChangeController::show
* @see app/Http/Controllers/Settings/EmailChangeController.php:60
* @route '/settings/email/confirm/{ulid}/{hash}'
*/
export const show = (args: { ulid: string | number, hash: string | number } | [ulid: string | number, hash: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/settings/email/confirm/{ulid}/{hash}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Settings\EmailChangeController::show
* @see app/Http/Controllers/Settings/EmailChangeController.php:60
* @route '/settings/email/confirm/{ulid}/{hash}'
*/
show.url = (args: { ulid: string | number, hash: string | number } | [ulid: string | number, hash: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            ulid: args[0],
            hash: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        ulid: args.ulid,
        hash: args.hash,
    }

    return show.definition.url
            .replace('{ulid}', parsedArgs.ulid.toString())
            .replace('{hash}', parsedArgs.hash.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\EmailChangeController::show
* @see app/Http/Controllers/Settings/EmailChangeController.php:60
* @route '/settings/email/confirm/{ulid}/{hash}'
*/
show.get = (args: { ulid: string | number, hash: string | number } | [ulid: string | number, hash: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Settings\EmailChangeController::show
* @see app/Http/Controllers/Settings/EmailChangeController.php:60
* @route '/settings/email/confirm/{ulid}/{hash}'
*/
show.head = (args: { ulid: string | number, hash: string | number } | [ulid: string | number, hash: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Settings\EmailChangeController::result
* @see app/Http/Controllers/Settings/EmailChangeController.php:89
* @route '/settings/email/confirmed'
*/
export const result = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: result.url(options),
    method: 'get',
})

result.definition = {
    methods: ["get","head"],
    url: '/settings/email/confirmed',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Settings\EmailChangeController::result
* @see app/Http/Controllers/Settings/EmailChangeController.php:89
* @route '/settings/email/confirmed'
*/
result.url = (options?: RouteQueryOptions) => {
    return result.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\EmailChangeController::result
* @see app/Http/Controllers/Settings/EmailChangeController.php:89
* @route '/settings/email/confirmed'
*/
result.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: result.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Settings\EmailChangeController::result
* @see app/Http/Controllers/Settings/EmailChangeController.php:89
* @route '/settings/email/confirmed'
*/
result.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: result.url(options),
    method: 'head',
})

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

/**
* @see \App\Http\Controllers\Settings\EmailChangeController::confirm
* @see app/Http/Controllers/Settings/EmailChangeController.php:75
* @route '/settings/email/confirm/{ulid}/{hash}'
*/
export const confirm = (args: { ulid: string | number, hash: string | number } | [ulid: string | number, hash: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: confirm.url(args, options),
    method: 'post',
})

confirm.definition = {
    methods: ["post"],
    url: '/settings/email/confirm/{ulid}/{hash}',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Settings\EmailChangeController::confirm
* @see app/Http/Controllers/Settings/EmailChangeController.php:75
* @route '/settings/email/confirm/{ulid}/{hash}'
*/
confirm.url = (args: { ulid: string | number, hash: string | number } | [ulid: string | number, hash: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            ulid: args[0],
            hash: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        ulid: args.ulid,
        hash: args.hash,
    }

    return confirm.definition.url
            .replace('{ulid}', parsedArgs.ulid.toString())
            .replace('{hash}', parsedArgs.hash.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\EmailChangeController::confirm
* @see app/Http/Controllers/Settings/EmailChangeController.php:75
* @route '/settings/email/confirm/{ulid}/{hash}'
*/
confirm.post = (args: { ulid: string | number, hash: string | number } | [ulid: string | number, hash: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: confirm.url(args, options),
    method: 'post',
})

const EmailChangeController = { show, result, update, resend, destroy, confirm }

export default EmailChangeController