import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../wayfinder'
/**
* @see \App\Http\Controllers\Dev\ComponentGalleryController::__invoke
* @see app/Http/Controllers/Dev/ComponentGalleryController.php:14
* @route '/dev/components'
*/
export const components = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: components.url(options),
    method: 'get',
})

components.definition = {
    methods: ["get","head"],
    url: '/dev/components',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Dev\ComponentGalleryController::__invoke
* @see app/Http/Controllers/Dev/ComponentGalleryController.php:14
* @route '/dev/components'
*/
components.url = (options?: RouteQueryOptions) => {
    return components.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Dev\ComponentGalleryController::__invoke
* @see app/Http/Controllers/Dev/ComponentGalleryController.php:14
* @route '/dev/components'
*/
components.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: components.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Dev\ComponentGalleryController::__invoke
* @see app/Http/Controllers/Dev/ComponentGalleryController.php:14
* @route '/dev/components'
*/
components.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: components.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Dev\LayoutPreviewController::__invoke
* @see app/Http/Controllers/Dev/LayoutPreviewController.php:14
* @route '/dev/layouts/{layout}'
*/
export const layouts = (args: { layout: string | number } | [layout: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: layouts.url(args, options),
    method: 'get',
})

layouts.definition = {
    methods: ["get","head"],
    url: '/dev/layouts/{layout}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Dev\LayoutPreviewController::__invoke
* @see app/Http/Controllers/Dev/LayoutPreviewController.php:14
* @route '/dev/layouts/{layout}'
*/
layouts.url = (args: { layout: string | number } | [layout: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { layout: args }
    }

    if (Array.isArray(args)) {
        args = {
            layout: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        layout: args.layout,
    }

    return layouts.definition.url
            .replace('{layout}', parsedArgs.layout.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Dev\LayoutPreviewController::__invoke
* @see app/Http/Controllers/Dev/LayoutPreviewController.php:14
* @route '/dev/layouts/{layout}'
*/
layouts.get = (args: { layout: string | number } | [layout: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: layouts.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Dev\LayoutPreviewController::__invoke
* @see app/Http/Controllers/Dev/LayoutPreviewController.php:14
* @route '/dev/layouts/{layout}'
*/
layouts.head = (args: { layout: string | number } | [layout: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: layouts.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Dev\MediaPreviewController::__invoke
* @see app/Http/Controllers/Dev/MediaPreviewController.php:25
* @route '/dev/media'
*/
export const media = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: media.url(options),
    method: 'get',
})

media.definition = {
    methods: ["get","head"],
    url: '/dev/media',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Dev\MediaPreviewController::__invoke
* @see app/Http/Controllers/Dev/MediaPreviewController.php:25
* @route '/dev/media'
*/
media.url = (options?: RouteQueryOptions) => {
    return media.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Dev\MediaPreviewController::__invoke
* @see app/Http/Controllers/Dev/MediaPreviewController.php:25
* @route '/dev/media'
*/
media.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: media.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Dev\MediaPreviewController::__invoke
* @see app/Http/Controllers/Dev/MediaPreviewController.php:25
* @route '/dev/media'
*/
media.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: media.url(options),
    method: 'head',
})

const dev = {
    components: Object.assign(components, components),
    layouts: Object.assign(layouts, layouts),
    media: Object.assign(media, media),
}

export default dev