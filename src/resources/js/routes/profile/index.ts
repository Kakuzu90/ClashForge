import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../wayfinder'
/**
* @see \App\Http\Controllers\Profile\ProfileController::show
* @see app/Http/Controllers/Profile/ProfileController.php:21
* @route '/u/{username}'
*/
export const show = (args: { username: string | number } | [username: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/u/{username}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Profile\ProfileController::show
* @see app/Http/Controllers/Profile/ProfileController.php:21
* @route '/u/{username}'
*/
show.url = (args: { username: string | number } | [username: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { username: args }
    }

    if (Array.isArray(args)) {
        args = {
            username: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        username: args.username,
    }

    return show.definition.url
            .replace('{username}', parsedArgs.username.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Profile\ProfileController::show
* @see app/Http/Controllers/Profile/ProfileController.php:21
* @route '/u/{username}'
*/
show.get = (args: { username: string | number } | [username: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Profile\ProfileController::show
* @see app/Http/Controllers/Profile/ProfileController.php:21
* @route '/u/{username}'
*/
show.head = (args: { username: string | number } | [username: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

const profile = {
    show: Object.assign(show, show),
}

export default profile