import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Accounts\VerificationController::show
* @see app/Http/Controllers/Accounts/VerificationController.php:27
* @route '/accounts/{ulid}/verify'
*/
export const show = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/accounts/{ulid}/verify',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Accounts\VerificationController::show
* @see app/Http/Controllers/Accounts/VerificationController.php:27
* @route '/accounts/{ulid}/verify'
*/
show.url = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { ulid: args }
    }

    if (Array.isArray(args)) {
        args = {
            ulid: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        ulid: args.ulid,
    }

    return show.definition.url
            .replace('{ulid}', parsedArgs.ulid.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Accounts\VerificationController::show
* @see app/Http/Controllers/Accounts/VerificationController.php:27
* @route '/accounts/{ulid}/verify'
*/
show.get = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Accounts\VerificationController::show
* @see app/Http/Controllers/Accounts/VerificationController.php:27
* @route '/accounts/{ulid}/verify'
*/
show.head = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Accounts\VerificationController::verified
* @see app/Http/Controllers/Accounts/VerificationController.php:59
* @route '/accounts/{ulid}/verified'
*/
export const verified = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: verified.url(args, options),
    method: 'get',
})

verified.definition = {
    methods: ["get","head"],
    url: '/accounts/{ulid}/verified',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Accounts\VerificationController::verified
* @see app/Http/Controllers/Accounts/VerificationController.php:59
* @route '/accounts/{ulid}/verified'
*/
verified.url = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { ulid: args }
    }

    if (Array.isArray(args)) {
        args = {
            ulid: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        ulid: args.ulid,
    }

    return verified.definition.url
            .replace('{ulid}', parsedArgs.ulid.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Accounts\VerificationController::verified
* @see app/Http/Controllers/Accounts/VerificationController.php:59
* @route '/accounts/{ulid}/verified'
*/
verified.get = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: verified.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Accounts\VerificationController::verified
* @see app/Http/Controllers/Accounts/VerificationController.php:59
* @route '/accounts/{ulid}/verified'
*/
verified.head = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: verified.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Accounts\VerificationController::verify
* @see app/Http/Controllers/Accounts/VerificationController.php:43
* @route '/accounts/{ulid}/verify'
*/
export const verify = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: verify.url(args, options),
    method: 'post',
})

verify.definition = {
    methods: ["post"],
    url: '/accounts/{ulid}/verify',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Accounts\VerificationController::verify
* @see app/Http/Controllers/Accounts/VerificationController.php:43
* @route '/accounts/{ulid}/verify'
*/
verify.url = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { ulid: args }
    }

    if (Array.isArray(args)) {
        args = {
            ulid: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        ulid: args.ulid,
    }

    return verify.definition.url
            .replace('{ulid}', parsedArgs.ulid.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Accounts\VerificationController::verify
* @see app/Http/Controllers/Accounts/VerificationController.php:43
* @route '/accounts/{ulid}/verify'
*/
verify.post = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: verify.url(args, options),
    method: 'post',
})

const VerificationController = { show, verified, verify }

export default VerificationController