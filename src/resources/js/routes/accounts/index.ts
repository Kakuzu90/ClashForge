import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../wayfinder'
import attachE7dcd8 from './attach'
import verify8ef1b2 from './verify'
/**
* @see \App\Http\Controllers\Accounts\AttachController::attach
* @see app/Http/Controllers/Accounts/AttachController.php:33
* @route '/accounts/attach'
*/
export const attach = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: attach.url(options),
    method: 'get',
})

attach.definition = {
    methods: ["get","head"],
    url: '/accounts/attach',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Accounts\AttachController::attach
* @see app/Http/Controllers/Accounts/AttachController.php:33
* @route '/accounts/attach'
*/
attach.url = (options?: RouteQueryOptions) => {
    return attach.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Accounts\AttachController::attach
* @see app/Http/Controllers/Accounts/AttachController.php:33
* @route '/accounts/attach'
*/
attach.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: attach.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Accounts\AttachController::attach
* @see app/Http/Controllers/Accounts/AttachController.php:33
* @route '/accounts/attach'
*/
attach.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: attach.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Accounts\VerificationController::verify
* @see app/Http/Controllers/Accounts/VerificationController.php:27
* @route '/accounts/{ulid}/verify'
*/
export const verify = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: verify.url(args, options),
    method: 'get',
})

verify.definition = {
    methods: ["get","head"],
    url: '/accounts/{ulid}/verify',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Accounts\VerificationController::verify
* @see app/Http/Controllers/Accounts/VerificationController.php:27
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
* @see app/Http/Controllers/Accounts/VerificationController.php:27
* @route '/accounts/{ulid}/verify'
*/
verify.get = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: verify.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Accounts\VerificationController::verify
* @see app/Http/Controllers/Accounts/VerificationController.php:27
* @route '/accounts/{ulid}/verify'
*/
verify.head = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: verify.url(args, options),
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
* @see \App\Http\Controllers\Accounts\AccountOwnershipController::destroy
* @see app/Http/Controllers/Accounts/AccountOwnershipController.php:19
* @route '/accounts/{ulid}'
*/
export const destroy = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/accounts/{ulid}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Accounts\AccountOwnershipController::destroy
* @see app/Http/Controllers/Accounts/AccountOwnershipController.php:19
* @route '/accounts/{ulid}'
*/
destroy.url = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return destroy.definition.url
            .replace('{ulid}', parsedArgs.ulid.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Accounts\AccountOwnershipController::destroy
* @see app/Http/Controllers/Accounts/AccountOwnershipController.php:19
* @route '/accounts/{ulid}'
*/
destroy.delete = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Accounts\AccountOwnershipController::featured
* @see app/Http/Controllers/Accounts/AccountOwnershipController.php:28
* @route '/accounts/{ulid}/featured'
*/
export const featured = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: featured.url(args, options),
    method: 'put',
})

featured.definition = {
    methods: ["put"],
    url: '/accounts/{ulid}/featured',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Accounts\AccountOwnershipController::featured
* @see app/Http/Controllers/Accounts/AccountOwnershipController.php:28
* @route '/accounts/{ulid}/featured'
*/
featured.url = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return featured.definition.url
            .replace('{ulid}', parsedArgs.ulid.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Accounts\AccountOwnershipController::featured
* @see app/Http/Controllers/Accounts/AccountOwnershipController.php:28
* @route '/accounts/{ulid}/featured'
*/
featured.put = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: featured.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Accounts\AccountOwnershipController::refresh
* @see app/Http/Controllers/Accounts/AccountOwnershipController.php:35
* @route '/accounts/{ulid}/refresh'
*/
export const refresh = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: refresh.url(args, options),
    method: 'post',
})

refresh.definition = {
    methods: ["post"],
    url: '/accounts/{ulid}/refresh',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Accounts\AccountOwnershipController::refresh
* @see app/Http/Controllers/Accounts/AccountOwnershipController.php:35
* @route '/accounts/{ulid}/refresh'
*/
refresh.url = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return refresh.definition.url
            .replace('{ulid}', parsedArgs.ulid.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Accounts\AccountOwnershipController::refresh
* @see app/Http/Controllers/Accounts/AccountOwnershipController.php:35
* @route '/accounts/{ulid}/refresh'
*/
refresh.post = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: refresh.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Accounts\AccountController::show
* @see app/Http/Controllers/Accounts/AccountController.php:24
* @route '/accounts/{ulid}'
*/
export const show = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/accounts/{ulid}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Accounts\AccountController::show
* @see app/Http/Controllers/Accounts/AccountController.php:24
* @route '/accounts/{ulid}'
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
* @see \App\Http\Controllers\Accounts\AccountController::show
* @see app/Http/Controllers/Accounts/AccountController.php:24
* @route '/accounts/{ulid}'
*/
show.get = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Accounts\AccountController::show
* @see app/Http/Controllers/Accounts/AccountController.php:24
* @route '/accounts/{ulid}'
*/
show.head = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

const accounts = {
    attach: Object.assign(attach, attachE7dcd8),
    verify: Object.assign(verify, verify8ef1b2),
    verified: Object.assign(verified, verified),
    destroy: Object.assign(destroy, destroy),
    featured: Object.assign(featured, featured),
    refresh: Object.assign(refresh, refresh),
    show: Object.assign(show, show),
}

export default accounts