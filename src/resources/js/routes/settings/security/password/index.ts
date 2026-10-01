import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Settings\SecurityController::update
* @see app/Http/Controllers/Settings/SecurityController.php:35
* @route '/settings/security/password'
*/
export const update = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/settings/security/password',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Settings\SecurityController::update
* @see app/Http/Controllers/Settings/SecurityController.php:35
* @route '/settings/security/password'
*/
update.url = (options?: RouteQueryOptions) => {
    return update.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\SecurityController::update
* @see app/Http/Controllers/Settings/SecurityController.php:35
* @route '/settings/security/password'
*/
update.put = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(options),
    method: 'put',
})

const password = {
    update: Object.assign(update, update),
}

export default password