import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
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

const email = {
    show: Object.assign(show, show),
    result: Object.assign(result, result),
    confirm: Object.assign(confirm, confirm),
}

export default email