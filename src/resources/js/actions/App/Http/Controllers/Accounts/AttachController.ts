import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Accounts\AttachController::create
* @see app/Http/Controllers/Accounts/AttachController.php:33
* @route '/accounts/attach'
*/
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/accounts/attach',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Accounts\AttachController::create
* @see app/Http/Controllers/Accounts/AttachController.php:33
* @route '/accounts/attach'
*/
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Accounts\AttachController::create
* @see app/Http/Controllers/Accounts/AttachController.php:33
* @route '/accounts/attach'
*/
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Accounts\AttachController::create
* @see app/Http/Controllers/Accounts/AttachController.php:33
* @route '/accounts/attach'
*/
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Accounts\AttachController::preview
* @see app/Http/Controllers/Accounts/AttachController.php:58
* @route '/accounts/attach/preview'
*/
export const preview = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: preview.url(options),
    method: 'post',
})

preview.definition = {
    methods: ["post"],
    url: '/accounts/attach/preview',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Accounts\AttachController::preview
* @see app/Http/Controllers/Accounts/AttachController.php:58
* @route '/accounts/attach/preview'
*/
preview.url = (options?: RouteQueryOptions) => {
    return preview.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Accounts\AttachController::preview
* @see app/Http/Controllers/Accounts/AttachController.php:58
* @route '/accounts/attach/preview'
*/
preview.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: preview.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Accounts\AttachController::store
* @see app/Http/Controllers/Accounts/AttachController.php:66
* @route '/accounts/attach'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/accounts/attach',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Accounts\AttachController::store
* @see app/Http/Controllers/Accounts/AttachController.php:66
* @route '/accounts/attach'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Accounts\AttachController::store
* @see app/Http/Controllers/Accounts/AttachController.php:66
* @route '/accounts/attach'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Accounts\AttachController::verifyTag
* @see app/Http/Controllers/Accounts/AttachController.php:90
* @route '/accounts/attach/verify-tag'
*/
export const verifyTag = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: verifyTag.url(options),
    method: 'post',
})

verifyTag.definition = {
    methods: ["post"],
    url: '/accounts/attach/verify-tag',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Accounts\AttachController::verifyTag
* @see app/Http/Controllers/Accounts/AttachController.php:90
* @route '/accounts/attach/verify-tag'
*/
verifyTag.url = (options?: RouteQueryOptions) => {
    return verifyTag.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Accounts\AttachController::verifyTag
* @see app/Http/Controllers/Accounts/AttachController.php:90
* @route '/accounts/attach/verify-tag'
*/
verifyTag.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: verifyTag.url(options),
    method: 'post',
})

const AttachController = { create, preview, store, verifyTag }

export default AttachController