import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \Laravel\Fortify\Http\Controllers\PasswordResetLinkController::create
* @see vendor/laravel/fortify/src/Http/Controllers/PasswordResetLinkController.php:22
* @route '/forgot-password'
*/
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/forgot-password',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Laravel\Fortify\Http\Controllers\PasswordResetLinkController::create
* @see vendor/laravel/fortify/src/Http/Controllers/PasswordResetLinkController.php:22
* @route '/forgot-password'
*/
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \Laravel\Fortify\Http\Controllers\PasswordResetLinkController::create
* @see vendor/laravel/fortify/src/Http/Controllers/PasswordResetLinkController.php:22
* @route '/forgot-password'
*/
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

/**
* @see \Laravel\Fortify\Http\Controllers\PasswordResetLinkController::create
* @see vendor/laravel/fortify/src/Http/Controllers/PasswordResetLinkController.php:22
* @route '/forgot-password'
*/
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

const PasswordResetLinkController = { create }

export default PasswordResetLinkController