<?php

use App\Rules\SafeHttpUrl;
use Illuminate\Support\Facades\Validator;

test('safe http url accepts http and https urls', function () {
    $rule = new SafeHttpUrl;

    foreach (['https://example.com', 'http://example.org/path'] as $url) {
        $validator = Validator::make(['url' => $url], ['url' => [$rule]]);
        expect($validator->passes())->toBeTrue();
    }
});

test('safe http url rejects dangerous and non web schemes', function () {
    $rule = new SafeHttpUrl;

    foreach ([
        'javascript:alert(1)',
        'data:text/html,test',
        'ftp://example.com',
        'file:///etc/passwd',
    ] as $url) {
        $validator = Validator::make(['url' => $url], ['url' => [$rule]]);
        expect($validator->fails())->toBeTrue();
    }
});
