import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Dev\ComponentGalleryController::__invoke
* @see app/Http/Controllers/Dev/ComponentGalleryController.php:14
* @route '/dev/components'
*/
const ComponentGalleryController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ComponentGalleryController.url(options),
    method: 'get',
})

ComponentGalleryController.definition = {
    methods: ["get","head"],
    url: '/dev/components',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Dev\ComponentGalleryController::__invoke
* @see app/Http/Controllers/Dev/ComponentGalleryController.php:14
* @route '/dev/components'
*/
ComponentGalleryController.url = (options?: RouteQueryOptions) => {
    return ComponentGalleryController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Dev\ComponentGalleryController::__invoke
* @see app/Http/Controllers/Dev/ComponentGalleryController.php:14
* @route '/dev/components'
*/
ComponentGalleryController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ComponentGalleryController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Dev\ComponentGalleryController::__invoke
* @see app/Http/Controllers/Dev/ComponentGalleryController.php:14
* @route '/dev/components'
*/
ComponentGalleryController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ComponentGalleryController.url(options),
    method: 'head',
})

export default ComponentGalleryController