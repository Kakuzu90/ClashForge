import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../wayfinder'
/**
* @see \App\Http\Controllers\Auth\RegisterController::store
* @see app/Http/Controllers/Auth/RegisterController.php:35
* @route '/register'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/register',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Auth\RegisterController::store
* @see app/Http/Controllers/Auth/RegisterController.php:35
* @route '/register'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\RegisterController::store
* @see app/Http/Controllers/Auth/RegisterController.php:35
* @route '/register'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Auth\RegisterController::sent
* @see app/Http/Controllers/Auth/RegisterController.php:47
* @route '/register/sent'
*/
export const sent = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: sent.url(options),
    method: 'get',
})

sent.definition = {
    methods: ["get","head"],
    url: '/register/sent',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Auth\RegisterController::sent
* @see app/Http/Controllers/Auth/RegisterController.php:47
* @route '/register/sent'
*/
sent.url = (options?: RouteQueryOptions) => {
    return sent.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\RegisterController::sent
* @see app/Http/Controllers/Auth/RegisterController.php:47
* @route '/register/sent'
*/
sent.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: sent.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Auth\RegisterController::sent
* @see app/Http/Controllers/Auth/RegisterController.php:47
* @route '/register/sent'
*/
sent.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: sent.url(options),
    method: 'head',
})

const register = {
    store: Object.assign(store, store),
    sent: Object.assign(sent, sent),
}

export default register