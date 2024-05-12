<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use DateTime;
use DB;

class APIController extends Controller
{
    /**
     * Get root url.
     *
     * @return \Illuminate\Http\Response
     */
    public function getIndex(Application $app)
    {
        return new JsonResponse(['message' => $app->version()]);
    }

    private function flushCacheWithPrefix($prefix)
    {
        $keys = Cache::getPrefix() . $prefix . '*';
        Cache::forget($keys);
    }

    private function buildCacheKey(Request $request)
    {
        $cacheKey = 'bookings_';
        $cacheKey .= 'p_' . $request->get('page', 1);
        if ($request->has('search')) {
            $cacheKey .= '_s_' . $request->input('search');
        }
        if ($request->has('sort_by') && $request->has('sort_order')) {
            $cacheKey .= '_sort_' . $request->input('sort_by') . '_' . $request->input('sort_order');
        }
        if ($request->has('per_page')) {
            $cacheKey .= '_perpage_' . $request->input('per_page');
        }
        return $cacheKey;
    }

    private function mappingBookingResponse($bookingsData) {
		// Initialize an array to store formatted bookings
		$bookings = [];

		// Loop through each booking data
		foreach ($bookingsData as $bookingData) {
		    // Initialize an array to store files
		    $files = [];

		    // Fetch associated files for the current booking
		    $associatedFiles = DB::table('files')
		        ->select('file_name', 'file_url')
		        ->where('booking_id', $bookingData->id)
		        ->get();

		    // Loop through each associated file
		    foreach ($associatedFiles as $file) {
		        // Push file details into the files array
		        $files[] = [
		            'file_name' => $file->file_name,
		            'src' => $file->file_url
		        ];
		    }

		    // Format booking data with associated files
		    $formattedBooking = [
		        'id' => $bookingData->id,
		        'customer_name' => $bookingData->customer_name,
		        'country_code' => $bookingData->country_code,
		        'customer_email' => $bookingData->customer_email,
		        'customer_phone' => $bookingData->customer_phone,
		        'surfing_experience' => $bookingData->surfing_experience,
		        'visit_date' => $bookingData->visit_date,
		        'desired_board' => $bookingData->desired_board,
		        'files' => $files
		    ];

		    // Push formatted booking data into the bookings array
		    $bookings[] = $formattedBooking;
		}

		// Get pagination metadata
		$paginationMetadata = [
		    'total' => $bookingsData->total(),
		    'per_page' => $bookingsData->perPage(),
		    'current_page' => $bookingsData->currentPage(),
		    'last_page' => $bookingsData->lastPage(),
		    'from' => $bookingsData->firstItem(),
		    'to' => $bookingsData->lastItem(),
		];

		// Combine bookings and pagination metadata
		$result = [
		    'data' => $bookings,
		    'metadata' => $paginationMetadata,
		    'message'=> $bookingsData->message,
		    'status_code'=>200
		];

		return $result;
    }

    public function getBookings(Request $request)
    {
        // Define cache key for this query
        $cacheKey = $this->buildCacheKey($request);

        // Check if data is available in cache
        if (Cache::has($cacheKey)) {
            // Retrieve data from cache
            $bookings = Cache::get($cacheKey);
        } else {
            // Retrieve bookings from the database
            $query = DB::table('bookings')
            ->select(
                "id",
                "customer_name",
                "country_code",
                "customer_email",
                "customer_phone",
                "surfing_experience",
                "visit_date",
                "desired_board"
            );

	        // Apply search filter if search term is provided
	        if ($request->has('search')) {
	            $searchTerm = $request->input('search');
	            $query->where('customer_name', 'like', '%' . $searchTerm . '%')
	                ->orWhere('customer_email', 'like', '%' . $searchTerm . '%')
	                ->orWhere('customer_phone', 'like', '%' . $searchTerm . '%');
	        }

	        // Apply order sorting
	        if ($request->has('sort_by') && $request->has('sort_order')) {
	            $sortBy = $request->input('sort_by');
	            $sortOrder = $request->input('sort_order');
	            $query->orderBy($sortBy, $sortOrder);
	        }

	        // Paginate the results
	        $perPage = $request->input('per_page', 10); // Default to 10 items per page
	        $currentPage = $request->input('page', 1); // Default to the first page
	        $bookings = $query->paginate($perPage, ['*'], 'page', $currentPage);

            // Cache the data with a TTL of 5 minutes (adjust as needed)
            Cache::put($cacheKey, $bookings, (new DateTime())->modify('+5 minutes'));
        }

        // Check if data is found
        if ($bookings->isEmpty()) {
            return new JsonResponse([
                'message' => "No bookings found based on the provided criteria.",
                'data' => [],
                'status_code'=>404
            ], 404); // Return HTTP 404 Not Found status code
        }

        $bookings->message = "Successfully retrieve bookings.";
        
        $final_response = $this->mappingBookingResponse($bookings);

        return new JsonResponse($final_response);
    }

    public function storeBooking(Application $app, Request $req)
	{
	    // Define validation rules
	    $rules = [
	        'customer_name' => 'required|string|max:255',
	        'country_code' => 'required|string|max:255|exists:countries,code',
	        'customer_email' => 'required|email|max:255',
	        'customer_phone' => 'required|string|max:255',
	        'surfing_experience' => 'required|numeric',
	        'visit_date' => 'required|date_format:d/m/Y',
	        'desired_board' => 'required|string|max:255',
	        'file_uploads.*.base64' => 'required|string',
	        'file_uploads.*.name' => 'required|string|max:255',
	        'file_uploads.*.size' => 'required|numeric',
	    ];

	    // Create a validator instance
	    $validator = Validator::make($req->all(), $rules);

	    // Check if validation fails
	    if ($validator->fails()) {

	    	$validation_message = "";

	    	$errors = $validator->errors()->toArray();
			$firstErrors = [];

			foreach ($errors as $field => $errorMessages) {
			    // Get the first error message for each field
			    $firstErrors[$field] = $errorMessages[0];
			}

			foreach ($firstErrors as $field => $errorMessage) {
			    $validation_message = $errorMessage;
			}

	        return new JsonResponse([
	            'message' => $validation_message,
	            'status_code' => 422
	        ], 200);
	    }

	    if (DB::table('bookings')->where("customer_email",$req->customer_email)->where("visit_date",$req->visit_date)->first()){
	    	return new JsonResponse([
	            'message' => $req->customer_email. " already book on ".$req->visit_date.". please choose another visit date.",
	            'status_code' => 422
	        ], 200);
	    }

	    // Insert booking if validation passes
	    
	    $reqInsert = [
	        "customer_name" => $req->customer_name,
	        "country_code" => $req->country_code,
	        "customer_email" => $req->customer_email,
	        "customer_phone" => $req->customer_phone,
	        "surfing_experience" => $req->surfing_experience,
	        "visit_date" => $req->visit_date,
	        "desired_board" => $req->desired_board,
	        "created_at" => date("Y-m-d H:i:s"),
	    ];

	    $booking = DB::table('bookings')->insertGetId($reqInsert);

	    if ($booking) {
	        // Handle file uploads
	        foreach ($req->file_uploads as $file) {
	            $uploadedFile = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $file['base64']));
	            $fileName = uniqid() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
	            $uploadPath = $_SERVER['DOCUMENT_ROOT'] . '/uploads';

	            // Create directory if not exists
	            if (!File::isDirectory($uploadPath)) {
	                File::makeDirectory($uploadPath, 0755, true, true);
	            }

	            // Save file to disk
	            file_put_contents($uploadPath . '/' . $fileName, $uploadedFile);

	            // Save file information to database
	            DB::table('files')->insert([
	                'file_name' => $file['name'],
	                'booking_id' => $booking,
	                'file_type' => pathinfo($file['name'], PATHINFO_EXTENSION),
	                'file_url' => url('/').'/uploads/' . $fileName
	            ]);
	        }

	        $this->flushCacheWithPrefix('bookings_');

	        return new JsonResponse([
	            'message' => "Successfully inserted booking with files.",
	            'data' => $reqInsert,
	            'status_code' => 201
	        ], 201);
	    }

	    return new JsonResponse([
	        'message' => "Failed to insert booking.",
	        'data' => null
	    ], 400);
	}

    public function updateBooking(Application $app, Request $req)
    {
    	// Define validation rules
	    $rules = [
	        'customer_name' => 'required|string|max:255',
	        'country_code' => 'required|string|max:255|exists:countries,code',
	        'customer_email' => 'required|email|max:255',
	        'customer_phone' => 'required|string|max:255',
	        'surfing_experience' => 'required|numeric',
	        'visit_date' => 'required|date_format:d/m/Y',
	        'desired_board' => 'required|string|max:255',
	    ];

	    // Create a validator instance
	    $validator = Validator::make($req->all(), $rules);

	    // Check if validation fails
	    if ($validator->fails()) {

	    	$validation_message = "";

	    	$errors = $validator->errors()->toArray();
			$firstErrors = [];

			foreach ($errors as $field => $errorMessages) {
			    // Get the first error message for each field
			    $firstErrors[$field] = $errorMessages[0];
			}

			foreach ($firstErrors as $field => $errorMessage) {
			    $validation_message = $errorMessage;
			}

	        return new JsonResponse([
	            'message' => $validation_message,
	            'status_code' => 422
	        ], 422);
	    }

	    // Update booking if validation passes
		$booking = DB::table('bookings')
		->where("id",$req->id)
		->update([
		    "customer_name" => $req->customer_name,
		    "country_code" => $req->country_code,
		    "customer_email" => $req->customer_email,
		    "customer_phone" => $req->customer_phone,
		    "surfing_experience" => $req->surfing_experience,
		    "visit_date" => $req->visit_date,
		    "desired_board" => $req->desired_board,
		    "created_at" => date("Y-m-d H:i:s")
		]);

	    if ($booking) {

	    	$this->flushCacheWithPrefix('bookings_');

	        return new JsonResponse([
	            'message' => "Successfully updated booking.",
	            'data' => $booking,
	            'status_code'=>200
	        ], 200);
	    }

	    return new JsonResponse([
	        'message' => "Failed to update booking.",
	        'data' => null
	    ], 400);

    }

    public function deleteBooking(Application $app, Request $req)
    {
    	$bookings = DB::table('bookings')->where("id",$req->id)->delete();

    	if ($bookings) {

    		$this->flushCacheWithPrefix('bookings_');

	        return new JsonResponse([
	        	'message' => "Successfully deleted booking.",
	        	'data'=>$bookings,
	        	'status_code'=>200
	        ],200);

    	}

    	return new JsonResponse([
        	'message' => "Failed to delete booking.",
        	'data'=>$bookings
        ],400);

    }

    public function createCountry(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:255|unique:countries,code',
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {

	    	$validation_message = "";

	    	$errors = $validator->errors()->toArray();
			$firstErrors = [];

			foreach ($errors as $field => $errorMessages) {
			    // Get the first error message for each field
			    $firstErrors[$field] = $errorMessages[0];
			}

			foreach ($firstErrors as $field => $errorMessage) {
			    $validation_message = $errorMessage;
			}

	        return new JsonResponse([
	            'message' => $validation_message,
	            'status_code' => 422
	        ], 422);
	    }

        $country = DB::table('countries')->insertGetId([
            'code' => $request->code,
            'name' => $request->name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($country) {

        	$this->flushCacheWithPrefix('cache_country_');

            return new JsonResponse([
                'message' => "Country created successfully.",
                'data' => $country,
                'status_code'=>201
            ], 201);
        }

        return new JsonResponse([
            'message' => "Failed to create country.",
            'data' => null,
            'status_code'=>400
        ], 400);
    }

    public function updateCountry(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:255|unique:countries,code,' . $id,
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {

	    	$validation_message = "";

	    	$errors = $validator->errors()->toArray();
			$firstErrors = [];

			foreach ($errors as $field => $errorMessages) {
			    // Get the first error message for each field
			    $firstErrors[$field] = $errorMessages[0];
			}

			foreach ($firstErrors as $field => $errorMessage) {
			    $validation_message = $errorMessage;
			}

	        return new JsonResponse([
	            'message' => $validation_message,
	            'status_code' => 422
	        ], 422);
	    }

        $updated = DB::table('countries')
            ->where('id', $id)
            ->update([
                'code' => $request->code,
                'name' => $request->name,
                'updated_at' => now(),
            ]);

        if ($updated) {

        	$this->flushCacheWithPrefix('cache_country_');

            return new JsonResponse([
                'message' => "Country updated successfully.",
                'data' => $updated,
                'status_code'=>200
            ], 200);
        }

        return new JsonResponse([
            'message' => "Failed to update country.",
            'data' => null,
            'status_code'=>400
        ], 400);
    }

    public function deleteCountry($id)
    {
        $deleted = DB::table('countries')->where('id', $id)->delete();

        if ($deleted) {

        	$this->flushCacheWithPrefix('cache_country_');

            return new JsonResponse([
                'message' => "Country deleted successfully.",
                'data' => $deleted,
                'status_code'=>200
            ], 200);
        }

        return new JsonResponse([
            'message' => "Failed to delete country.",
            'data' => null
        ], 400);
    }

    private function formatCountriesResponse($countriesData)
    {
        if ($countriesData->isEmpty()) {
            return [
                'message' => "No countries found.",
                'data' => [],
                'status_code' => 404
            ];
        }

        return [
            'message' => "Successfully retrieved countries.",
            'data' => $countriesData,
            'status_code'=>200
        ];
    }

    private function buildCacheKeyForCountries(Request $request)
    {
        $cacheKey = 'cache_country_';
        $cacheKey .= 'p_' . $request->get('page', 1);
        if ($request->has('search')) {
            $cacheKey .= '_s_' . $request->input('search');
        }
        if ($request->has('sort_by') && $request->has('sort_order')) {
            $cacheKey .= '_sort_' . $request->input('sort_by') . '_' . $request->input('sort_order');
        }
        if ($request->has('per_page')) {
            $cacheKey .= '_perpage_' . $request->input('per_page');
        }
        return $cacheKey;
    }

    public function getCountries(Request $request)
    {
        // Define cache key for countries
        $cacheKey = $this->buildCacheKeyForCountries($request);

        // Check if countries data is available in cache
        if (Cache::has($cacheKey)) {
            // Retrieve countries data from cache
            $countries = Cache::get($cacheKey);
        } else {
            // Query to fetch countries from the database
            $query = DB::table('countries');

            // Apply search filter if search term is provided
            if ($request->has('search')) {
                $searchTerm = $request->input('search');
                $query->where('name', 'like', '%' . $searchTerm . '%')
                    ->orWhere('code', 'like', '%' . $searchTerm . '%');
            }

            // Apply sorting
            if ($request->has('sort_by') && $request->has('sort_order')) {
                $sortBy = $request->input('sort_by');
                $sortOrder = $request->input('sort_order');
                $query->orderBy($sortBy, $sortOrder);
            }

            // Paginate the results
            $perPage = $request->input('per_page', 10); // Default to 10 items per page
            $currentPage = $request->input('page', 1); // Default to the first page
            $countries = $query->paginate($perPage, ['*'], 'page', $currentPage);

            // Cache the data with a TTL of 1 hour
            Cache::put($cacheKey, $countries, (new DateTime())->modify('+5 minutes'));
        }

        // Check if countries data is found
        if ($countries->isEmpty()) {
            return new JsonResponse([
                'message' => "No countries found based on the provided criteria.",
                'data' => [],
                'status_code'=>404
            ], 404); // Return HTTP 404 Not Found status code
        }

        // Format the response
        $response = [
            'message' => "Successfully retrieved countries.",
            'data' => $countries,
            'metadata' => [
                'total' => $countries->total(),
                'per_page' => $countries->perPage(),
                'current_page' => $countries->currentPage(),
                'last_page' => $countries->lastPage(),
                'from' => $countries->firstItem(),
                'to' => $countries->lastItem(),
            ],
            'status_code'=>200
        ];

        return new JsonResponse($response);
    }
}
