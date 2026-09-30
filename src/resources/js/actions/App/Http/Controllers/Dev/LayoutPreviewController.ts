import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Dev\LayoutPreviewController::__invoke
* @see app/Http/Controllers/Dev/LayoutPreviewController.php:14
* @route '/dev/layouts/{layout}'
*/
const LayoutPreviewController = (args: { layout: string | number } | [layout: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: LayoutPreviewController.url(args, options),
    method: 'get',
})

LayoutPreviewController.definition = {
    methods: ["get","head"],
    url: '/dev/layouts/{layout}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Dev\LayoutPreviewController::__invoke
* @see app/Http/Controllers/Dev/LayoutPreviewController.php:14
* @route '/dev/layouts/{layout}'
*/
LayoutPreviewController.url = (args: { layout: string | number } | [layout: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return LayoutPreviewController.definition.url
            .replace('{layout}', parsedArgs.layout.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Dev\LayoutPreviewController::__invoke
* @see app/Http/Controllers/Dev/LayoutPreviewController.php:14
* @route '/dev/layouts/{layout}'
*/
LayoutPreviewController.get = (args: { layout: string | number } | [layout: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: LayoutPreviewController.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Dev\LayoutPreviewController::__invoke
* @see app/Http/Controllers/Dev/LayoutPreviewController.php:14
* @route '/dev/layouts/{layout}'
*/
LayoutPreviewController.head = (args: { layout: string | number } | [layout: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: LayoutPreviewController.url(args, options),
    method: 'head',
})

export default LayoutPreviewController