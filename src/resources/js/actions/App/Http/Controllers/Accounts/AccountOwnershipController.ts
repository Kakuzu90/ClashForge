import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
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
* @see \App\Http\Controllers\Accounts\AccountOwnershipController::feature
* @see app/Http/Controllers/Accounts/AccountOwnershipController.php:28
* @route '/accounts/{ulid}/featured'
*/
export const feature = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: feature.url(args, options),
    method: 'put',
})

feature.definition = {
    methods: ["put"],
    url: '/accounts/{ulid}/featured',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Accounts\AccountOwnershipController::feature
* @see app/Http/Controllers/Accounts/AccountOwnershipController.php:28
* @route '/accounts/{ulid}/featured'
*/
feature.url = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return feature.definition.url
            .replace('{ulid}', parsedArgs.ulid.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Accounts\AccountOwnershipController::feature
* @see app/Http/Controllers/Accounts/AccountOwnershipController.php:28
* @route '/accounts/{ulid}/featured'
*/
feature.put = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: feature.url(args, options),
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

const AccountOwnershipController = { destroy, feature, refresh }

export default AccountOwnershipController