import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Auth\ResetLinkRequestController::store
* @see app/Http/Controllers/Auth/ResetLinkRequestController.php:17
* @route '/forgot-password'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/forgot-password',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Auth\ResetLinkRequestController::store
* @see app/Http/Controllers/Auth/ResetLinkRequestController.php:17
* @route '/forgot-password'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\ResetLinkRequestController::store
* @see app/Http/Controllers/Auth/ResetLinkRequestController.php:17
* @route '/forgot-password'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

const ResetLinkRequestController = { store }

export default ResetLinkRequestController