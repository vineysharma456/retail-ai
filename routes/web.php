<?php

use Illuminate\Support\Facades\Route;
use App\Tools\SearchProducts;
use App\Tools\GetProduct;
use App\Tools\GetOrder;
use App\Tools\EvaluateReturn;
use App\Services\OpenAIService;


Route::get('/', function () {
    return view('welcome');
});


Route::get('/test-products', function () {

    $csv = new \App\Services\CsvService();

    return $csv->getProducts();
});

Route::get('/test-search', function () {

    $tool = new SearchProducts();

    return $tool->execute();
});

Route::get('/test-product', function () {

    $tool = new GetProduct();

    return $tool->execute('P0028');
}); 

Route::get('/test-order', function () {

    $tool = new GetOrder();

    return $tool->execute('INVALID999');
}); 

Route::get('/test-return', function () {

    $tool = new EvaluateReturn();

    return $tool->execute('1043');
});

Route::get('/ai-test', function () {

    $ai = new OpenAIService();

    return $ai->chat(
        'I need a modest evening gown under $300 in size 8. I prefer something on sale.'
    );
});