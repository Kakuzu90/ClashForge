import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\DisputeController::index
* @see app/Http/Controllers/Admin/DisputeController.php:36
* @route '/admin/disputes'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/admin/disputes',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\DisputeController::index
* @see app/Http/Controllers/Admin/DisputeController.php:36
* @route '/admin/disputes'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\DisputeController::index
* @see app/Http/Controllers/Admin/DisputeController.php:36
* @route '/admin/disputes'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\DisputeController::index
* @see app/Http/Controllers/Admin/DisputeController.php:36
* @route '/admin/disputes'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Admin\DisputeController::show
* @see app/Http/Controllers/Admin/DisputeController.php:58
* @route '/admin/disputes/{ulid}'
*/
export const show = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/admin/disputes/{ulid}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\DisputeController::show
* @see app/Http/Controllers/Admin/DisputeController.php:58
* @route '/admin/disputes/{ulid}'
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
* @see \App\Http\Controllers\Admin\DisputeController::show
* @see app/Http/Controllers/Admin/DisputeController.php:58
* @route '/admin/disputes/{ulid}'
*/
show.get = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\DisputeController::show
* @see app/Http/Controllers/Admin/DisputeController.php:58
* @route '/admin/disputes/{ulid}'
*/
show.head = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Admin\DisputeController::decide
* @see app/Http/Controllers/Admin/DisputeController.php:75
* @route '/admin/disputes/{ulid}/decision'
*/
export const decide = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: decide.url(args, options),
    method: 'post',
})

decide.definition = {
    methods: ["post"],
    url: '/admin/disputes/{ulid}/decision',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\DisputeController::decide
* @see app/Http/Controllers/Admin/DisputeController.php:75
* @route '/admin/disputes/{ulid}/decision'
*/
decide.url = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return decide.definition.url
            .replace('{ulid}', parsedArgs.ulid.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\DisputeController::decide
* @see app/Http/Controllers/Admin/DisputeController.php:75
* @route '/admin/disputes/{ulid}/decision'
*/
decide.post = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: decide.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Admin\DisputeController::releaseTag
* @see app/Http/Controllers/Admin/DisputeController.php:92
* @route '/admin/disputes/{ulid}/release-tag'
*/
export const releaseTag = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: releaseTag.url(args, options),
    method: 'post',
})

releaseTag.definition = {
    methods: ["post"],
    url: '/admin/disputes/{ulid}/release-tag',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\DisputeController::releaseTag
* @see app/Http/Controllers/Admin/DisputeController.php:92
* @route '/admin/disputes/{ulid}/release-tag'
*/
releaseTag.url = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return releaseTag.definition.url
            .replace('{ulid}', parsedArgs.ulid.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\DisputeController::releaseTag
* @see app/Http/Controllers/Admin/DisputeController.php:92
* @route '/admin/disputes/{ulid}/release-tag'
*/
releaseTag.post = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: releaseTag.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Admin\DisputeController::removeEvidence
* @see app/Http/Controllers/Admin/DisputeController.php:103
* @route '/admin/disputes/{ulid}/evidence/{media}'
*/
export const removeEvidence = (args: { ulid: string | number, media: string | number } | [ulid: string | number, media: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: removeEvidence.url(args, options),
    method: 'delete',
})

removeEvidence.definition = {
    methods: ["delete"],
    url: '/admin/disputes/{ulid}/evidence/{media}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Admin\DisputeController::removeEvidence
* @see app/Http/Controllers/Admin/DisputeController.php:103
* @route '/admin/disputes/{ulid}/evidence/{media}'
*/
removeEvidence.url = (args: { ulid: string | number, media: string | number } | [ulid: string | number, media: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            ulid: args[0],
            media: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        ulid: args.ulid,
        media: args.media,
    }

    return removeEvidence.definition.url
            .replace('{ulid}', parsedArgs.ulid.toString())
            .replace('{media}', parsedArgs.media.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\DisputeController::removeEvidence
* @see app/Http/Controllers/Admin/DisputeController.php:103
* @route '/admin/disputes/{ulid}/evidence/{media}'
*/
removeEvidence.delete = (args: { ulid: string | number, media: string | number } | [ulid: string | number, media: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: removeEvidence.url(args, options),
    method: 'delete',
})

const DisputeController = { index, show, decide, releaseTag, removeEvidence }

export default DisputeController