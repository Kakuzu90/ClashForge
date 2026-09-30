import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Upload\UploadController::intent
* @see app/Http/Controllers/Upload/UploadController.php:20
* @route '/uploads/intent'
*/
export const intent = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: intent.url(options),
    method: 'post',
})

intent.definition = {
    methods: ["post"],
    url: '/uploads/intent',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Upload\UploadController::intent
* @see app/Http/Controllers/Upload/UploadController.php:20
* @route '/uploads/intent'
*/
intent.url = (options?: RouteQueryOptions) => {
    return intent.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Upload\UploadController::intent
* @see app/Http/Controllers/Upload/UploadController.php:20
* @route '/uploads/intent'
*/
intent.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: intent.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Upload\UploadController::complete
* @see app/Http/Controllers/Upload/UploadController.php:25
* @route '/uploads/{media}/complete'
*/
export const complete = (args: { media: string | number } | [media: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: complete.url(args, options),
    method: 'post',
})

complete.definition = {
    methods: ["post"],
    url: '/uploads/{media}/complete',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Upload\UploadController::complete
* @see app/Http/Controllers/Upload/UploadController.php:25
* @route '/uploads/{media}/complete'
*/
complete.url = (args: { media: string | number } | [media: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { media: args }
    }

    if (Array.isArray(args)) {
        args = {
            media: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        media: args.media,
    }

    return complete.definition.url
            .replace('{media}', parsedArgs.media.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Upload\UploadController::complete
* @see app/Http/Controllers/Upload/UploadController.php:25
* @route '/uploads/{media}/complete'
*/
complete.post = (args: { media: string | number } | [media: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: complete.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Upload\UploadController::show
* @see app/Http/Controllers/Upload/UploadController.php:30
* @route '/uploads/{media}'
*/
export const show = (args: { media: string | number } | [media: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/uploads/{media}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Upload\UploadController::show
* @see app/Http/Controllers/Upload/UploadController.php:30
* @route '/uploads/{media}'
*/
show.url = (args: { media: string | number } | [media: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { media: args }
    }

    if (Array.isArray(args)) {
        args = {
            media: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        media: args.media,
    }

    return show.definition.url
            .replace('{media}', parsedArgs.media.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Upload\UploadController::show
* @see app/Http/Controllers/Upload/UploadController.php:30
* @route '/uploads/{media}'
*/
show.get = (args: { media: string | number } | [media: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Upload\UploadController::show
* @see app/Http/Controllers/Upload/UploadController.php:30
* @route '/uploads/{media}'
*/
show.head = (args: { media: string | number } | [media: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

const UploadController = { intent, complete, show }

export default UploadController