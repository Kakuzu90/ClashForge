import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../wayfinder'
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

const attach = {
    preview: Object.assign(preview, preview),
    store: Object.assign(store, store),
    verifyTag: Object.assign(verifyTag, verifyTag),
}

export default attach