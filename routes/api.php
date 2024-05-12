<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$api = $app->make(Dingo\Api\Routing\Router::class);

$api->version('v1', function ($api) {

    // Auth
    $api->post('/auth/login', [
        'as' => 'api.auth.login',
        'uses' => 'App\Http\Controllers\Auth\AuthController@postLogin',
    ]);

    $api->post('/auth/token', [
        'as' => 'api.auth.token',
        'uses' => 'App\Http\Controllers\Auth\AuthController@postToken',
    ]);

    $api->group([
        'middleware' => 'api.auth',
    ], function ($api) {
        $api->get('/', [
            'uses' => 'App\Http\Controllers\APIController@getIndex',
            'as' => 'api.index'
        ]);

        // Booking API
        $api->get('/bookings', [
            'uses' => 'App\Http\Controllers\APIController@getBookings',
            'as' => 'api.bookings'
        ]);

        $api->post('/booking/store', [
            'uses' => 'App\Http\Controllers\APIController@storeBooking',
            'as' => 'api.booking.store'
        ]);

        $api->put('/booking/update', [
            'uses' => 'App\Http\Controllers\APIController@updateBooking',
            'as' => 'api.booking.update'
        ]);

        $api->delete('/booking/delete', [
            'uses' => 'App\Http\Controllers\APIController@deleteBooking',
            'as' => 'api.booking.delete'
        ]);

        // Country API
        $api->get('/countries', [
            'uses' => 'App\Http\Controllers\APIController@getCountries',
            'as' => 'api.countries'
        ]);

        $api->post('/country/store', [
            'uses' => 'App\Http\Controllers\APIController@storeCountry',
            'as' => 'api.country.store'
        ]);

        $api->put('/country/update', [
            'uses' => 'App\Http\Controllers\APIController@updateCountry',
            'as' => 'api.country.update'
        ]);

        $api->delete('/country/delete', [
            'uses' => 'App\Http\Controllers\APIController@deleteCountry',
            'as' => 'api.country.delete'
        ]);

        // USER

        $api->get('/auth/user', [
            'uses' => 'App\Http\Controllers\Auth\AuthController@getUser',
            'as' => 'api.auth.user'
        ]);
        $api->patch('/auth/refresh', [
            'uses' => 'App\Http\Controllers\Auth\AuthController@patchRefresh',
            'as' => 'api.auth.refresh'
        ]);
        $api->delete('/auth/invalidate', [
            'uses' => 'App\Http\Controllers\Auth\AuthController@deleteInvalidate',
            'as' => 'api.auth.invalidate'
        ]);
    });
});
