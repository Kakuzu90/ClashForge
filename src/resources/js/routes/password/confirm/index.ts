import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../wayfinder'
/**
* @see \Laravel\Fortify\Http\Controllers\ConfirmablePasswordController::store
* @see vendor/laravel/fortify/src/Http/Controllers/ConfirmablePasswordController.php:51
* @route '/confirm-password'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/confirm-password',
} satisfies RouteDefinition<["post"]>

/**
* @see \Laravel\Fortify\Http\Controllers\ConfirmablePasswordController::store
* @see vendor/laravel/fortify/src/Http/Controllers/ConfirmablePasswordController.php:51
* @route '/confirm-password'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \Laravel\Fortify\Http\Controllers\ConfirmablePasswordController::store
* @see vendor/laravel/fortify/src/Http/Controllers/ConfirmablePasswordController.php:51
* @route '/confirm-password'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

const confirm = {
    store: Object.assign(store, store),
}

export default confirm