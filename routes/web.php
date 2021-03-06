<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the Closure to execute when that URI is requested.
|
*/

use App\Article;
use App\Collection;
use App\Elasticsearch\Repositories\AuthorityRepository;
use App\Elasticsearch\Repositories\ItemRepository;
use App\Filter\ItemFilter;
use App\Item;
use App\Notice;
use App\Order;
use App\FeaturedPiece as Slide;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Session;

Route::group(['domain' => 'media.webumenia.{tld}'], function () {
    Route::get('/', function ($tld) {
        return "webumenia media server";
    });
    Route::get('{id}', function ($tld, $id) {
        $item = Item::find($id);
        if ($item) {
            return config('app.url') . $item->getImagePath();
        }
    });
});

Route::group([
    'prefix' => LaravelLocalization::setLocale(),
    'middleware' => [ 'localeSessionRedirect', 'localizationRedirect' ]
],
function()
{
    Route::get('/', 'HomeController@index');
    Route::get('leto', function () {

        return redirect('kolekcia/25');
    });

    Route::get('objednavka', function () {

        $items = Item::with('images')->find(Session::get('cart', array()));

        $allow_printed_reproductions = $items->reduce(function ($result, $item) {
            return $result & !$item->images->isEmpty();
        }, true);

        return view('objednavka', [
            'items' => $items,
            'allow_printed_reproductions' => $allow_printed_reproductions,
            'notice' => Notice::current(),
        ]);
    })->name('frontend.reproduction.detail');

    Route::post('objednavka', function () {

        $input = Request::all();

        $rules = Order::$rules;
        $v = Validator::make($input, $rules);
        $v->sometimes('purpose', 'required|max:1500', function ($input) {

            return $input->format == 'digit??lna reprodukcia';
        });
        $v->sometimes('delivery_point', 'required', function ($input) {

            return $input->format != 'digit??lna reprodukcia';
        });

        if ($v->passes()) {
            $order = new Order();
            $order->name = Request::input('name');
            $order->address = Request::input('address');
            $order->email = Request::input('email');
            $order->phone = Request::input('phone');
            $order->format = Request::input('format');
            $order->frame = Request::input('frame');
            $order->purpose_kind = Request::input('purpose_kind');
            $order->purpose = Request::input('purpose');
            $order->delivery_point = Request::input('delivery_point', null);
            $order->note = Request::input('note');
            $order->save();

            $item_ids = explode(', ', Request::input('pids'));

            foreach ($item_ids as $item_id) {
                $order->items()->attach($item_id);
            }

            $type = (Request::input('format') == 'digit??lna reprodukcia') ? 'digit??lna' : 'tla??en??';

            //poslat objednavku do Jiry
            $client = new GuzzleHttp\Client();
            $res = $client->post('https://jira.sng.sk/rest/cedvu/latest/order/create', [
                'auth' => [Config::get('app.jira_auth.user'), Config::get('app.jira_auth.pass')],
                'form_params' => [
                    'pids' => Request::input('pids'),
                    'organization' => $order->name,
                    'contactPerson' => $order->name,
                    'email' => $order->email,
                    'kindOfPurpose' => $order->purpose_kind,
                    'purpose' => $order->purpose,
                    'medium' => 'In??',
                    'address' => $order->address,
                    'phone' => $order->phone,
                    'ico' => '',
                    'dic' => '',
                    'numOfCopies' => '1',
                    'reproductionType' => $type,
                    'format' => $order->format,
                    'frameColor' => $order->frame,
                    'deliveryPoint' => $order->delivery_point,
                    'note' => $order->note,
                ],
            ]);
            if ($res->getStatusCode() == 200) {
                Session::forget('cart');

                return redirect('dakujeme');
            } else {
                Session::flash('message', 'Nastal probl??m pri ulo??en?? va??ej objedn??vky. Pros??m kontaktujte lab@sng.sk. ');

                return Redirect::back()->withInput();
            }
        }

        return Redirect::back()->withInput()->withErrors($v);

    })->name('objednavka.post');

    Route::get('dakujeme', function () {

        return view('dakujeme');
    });

    Route::get('dielo/{id}/zoom', 'ZoomController@getIndex')->name('item.zoom');

    Route::get('ukaz_skicare', 'SkicareController@index');
    Route::get('skicare', 'SkicareController@getList');
    Route::get('dielo/{id}/skicar', 'SkicareController@getZoom');

    Route::get('dielo/{id}/objednat', function ($id) {

        $item = Item::find($id);

        if (empty($item) || !$item->isForReproduction()) {
            abort(404);
        }

        if (!in_array($item->id, Session::get('cart', array()))) {
            Session::push('cart', $item->id);
        }

        Session::flash( 'message', trans('objednavka.message_add_order', ['artwork_description' => '<b>'.$item->getTitleWithAuthors().'</b> ('.$item->getDatingFormated().')']) );

        return redirect($item->getUrl());

    });

    Route::get('dielo/{id}/odstranit', function ($id) {

        $item = Item::find($id);

        if (empty($item)) {
            abort(404);
        }
        Session::put('cart', array_diff(Session::get('cart', []), [$item->id]));
        Session::flash('message', trans('objednavka.message_remove_order', ['artwork_description' => '<b>'.$item->getTitleWithAuthors().'</b> ('.$item->getDatingFormated().')']) );

        return Redirect::back();

    });

    Route::get('dielo/{id}/stiahnut', ['middleware' => 'throttle:5,1', function ($id) {
        $item = Item::findOrFail($id);
        if ($item->images->isEmpty()) {
            abort(404);
        }

        return redirect()->route('image.download', ['id' => $item->images->first()->id]);
    }]);

    Route::get('dielo/{id}', function ($id, ItemRepository $itemRepository) {
        /** @var Item $item */
        $item = Item::find($id);
        if (empty($item)) {
            abort(404);
        }
        $item->timestamps = false;
        $item->view_count += 1;
        $item->save();
        $previous = $next = false;

        $similar_items = $itemRepository->getSimilar(12, $item)->getCollection();
        $related_items = (!empty($item->related_work)) ? Item::related($item)->get() : null;

        if (Request::has('collection')) {
            $collection = Collection::find((int) Request::input('collection'));
            if (!empty($collection)) {
                $items = $collection->items->pluck('id')->all();
                $previousId = getPrevVal($items, $id);
                if ($previousId) {
                    $previous = Item::find($previousId)->getUrl(['collection' => $collection->id]);
                }
                $nextId = getNextVal($items, $id);
                if ($nextId) {
                    $next = Item::find($nextId)->getUrl(['collection' => $collection->id]);
                }
            }
        }

        $gtm_data_layer = [
            'artwork' => [
                'authors' => array_values($item->authors),
                'work_types' => collect($item->work_types)->pluck(['path']),
                'topic ' => $item->topic,
                'media' => $item->mediums,
                'technique' => $item->technique,
                'related_work' => $item->related_work,
            ]
        ];

        return view('dielo', compact(
            'item',
            'similar_items',
            'related_items',
            'previous',
            'next',
            'gtm_data_layer'
        ));
    })->name('dielo');

    Route::get('dielo/{id}/colorrelated', function ($id, ItemRepository $itemRepository) {
        $item = Item::findOrFail($id);
        return view('dielo-colorrelated', [
            'similar_by_color' => $itemRepository->getSimilarByColor(12, $item)->getCollection(),
        ]);
    })->name('dielo.colorrelated');

    Route::get('dielo/nahlad/{id}/{width}/{height?}', 'ImageController@resize')->where('width', '[0-9]+')->where('height', '[0-9]+')->name('dielo.nahlad');
    Route::get('image/{id}/download', 'ImageController@download')->name('image.download');

    Route::get('patternlib', 'PatternlibController@getIndex')->name('frontend.patternlib.index');

    Route::get('katalog', 'CatalogController@getIndex')->name('frontend.catalog.index');
    Route::get('katalog/suggestions', 'CatalogController@getSuggestions')->name('frontend.catalog.suggestions');
    Route::get('katalog/random', 'CatalogController@getRandom')->name('frontend.catalog.random');

    Route::match(array('GET', 'POST'), 'autori', 'AuthorController@getIndex')->name('frontend.author.index');
    Route::match(array('GET', 'POST'), 'autori/suggestions', 'AuthorController@getSuggestions')->name('frontend.author.suggestions');
    Route::get('autor/{id}', 'AuthorController@getDetail')->name('frontend.author.detail');

    Route::match(array('GET', 'POST'), 'clanky', 'ClanokController@getIndex')->name('frontend.article.index');
    Route::match(array('GET', 'POST'), 'clanky/suggestions', 'ClanokController@getSuggestions');
    Route::get('clanok/{slug}', 'ClanokController@getDetail')->name('frontend.article.detail');

    Route::match(array('GET', 'POST'), 'kolekcie', 'KolekciaController@getIndex')->name('frontend.collection.index');
    Route::match(array('GET', 'POST'), 'kolekcie/suggestions', 'KolekciaController@getSuggestions')->name('frontend.collection.suggestions');
    Route::get('kolekcia/{slug}', 'KolekciaController@getDetail')->name('frontend.collection.detail');
    Route::get('oblubene', 'UserCollectionController@show')->name('frontend.user-collection.show');
    Route::resource('oblubene', 'SharedUserCollectionController')
        ->names('frontend.shared-user-collections')
        ->parameters(['oblubene' => 'collection:public_id'])
        ->except('index');
    Route::resource('edu', 'EducationalArticleController')
        ->names('frontend.educational-article')
        ->parameters(['edu' => 'article:slug']);

    Route::get('informacie', function (ItemRepository $itemRepository) {
        $for_reproduction_filter = (new ItemFilter)->setHasImage(true)->setHasIip(true)->setIsForReproduction(true);
        $items_for_reproduction_search = $itemRepository->getRandom(20, $for_reproduction_filter);
        $items_for_reproduction_total =  formatNum($itemRepository->count((new ItemFilter)->setIsForReproduction(true)));
        $items_for_reproduction_sample = $items_for_reproduction_search->getCollection();

        $galleries = config('galleries');

        return view('informacie', compact(
            'galleries',
            'items_for_reproduction_sample',
            'items_for_reproduction_total'
        ));
    })->name('frontend.info');

    Route::get('reprodukcie', function (ItemRepository $itemRepository) {
        $collection = Collection::find('55');

        $filter = (new ItemFilter)->setIsForReproduction(true)->setHasImage(true)->setHasIip(true);

        if ($collection) {
            $items_recommended = $collection->items()->inRandomOrder()->take(20)->get();
        } else {
            $items_recommended = $itemRepository->getRandom(20, $filter)->getCollection();
        }

        $response = $itemRepository->getRandom(20, $filter);
        $total =  formatNum($itemRepository->count((new ItemFilter)->setIsForReproduction(true)));

        return view('reprodukcie', [
            'items_recommended' => $items_recommended,
            'items' => $response->getCollection(),
            'total' => $total,
            'notice' => Notice::current(),
        ]);
    })->name('frontend.reproduction.index');
});

Route::group(array('middleware' => 'guest'), function () {
    Route::get('login', 'AuthController@getLogin');
    Route::post('login', 'AuthController@postLogin');
});

Route::group(['middleware' => ['auth', 'can:edit']], function () {
    Route::get('admin', 'AdminController@index');
    Route::get('logout', 'AuthController@logout');
    Route::get('imports/launch/{id}', 'ImportController@launch');
    Route::resource('imports', 'ImportController');
    Route::get('item/search', 'ItemController@search');

    Route::get('item', 'ItemController@index')->name('item.index');
    Route::get('item/{id}/show', 'ItemController@show')->name('item.show');
    Route::match(['get', 'post'], 'item/create', 'ItemController@create')->name('item.create');
    Route::match(['get', 'post'], 'item/{id}/edit', 'ItemController@edit')->name('item.edit');

    Route::post('item/destroySelected', 'ItemController@destroySelected');
});

Route::group(['middleware' => ['auth', 'can:edit']], function () {

    Route::post('dielo/{id}/addTags', function($id)
    {
        $item = Item::find($id);
        $newTags = Request::input('tags');

        if (empty($newTags)) {
            Session::flash( 'message', trans('Neboli zadadn?? ??iadne nov?? tagy.') );
            return Redirect::to($item->getUrl());
        }

        // @TODO take back captcha if opened for all users
        foreach ($newTags as $newTag) {
            $item->tag($newTag);
        }

        Session::flash( 'message', trans('Bolo pridan??ch ' . count($newTags) . ' tagov. ??akujeme!') );

        // validate that user is human with recaptcha
        // till it's for authorised users only, temporary disable
        /*
        $secret = config('app.google_recaptcha_secret');
        $recaptcha = new \ReCaptcha\ReCaptcha($secret);
        $resp = $recaptcha->verify($_POST['g-recaptcha-response'], $_SERVER['REMOTE_ADDR']);
        if ($resp->isSuccess()) {
            // add new tags
            foreach ($newTags as $newTag) {
                $item->tag($newTag);
            }
        } else {
            // validation unsuccessful
            return Redirect::to($item->getUrl());
        }
        */

        return Redirect::to($item->getUrl());
    });

    Route::resource('article', 'ArticleController');
    Route::get('collection/{collection_id}/detach/{item_id}', 'CollectionController@detach');
    Route::post('collection/fill', 'CollectionController@fill');
    Route::post('collection/sort', 'CollectionController@sort');
    Route::resource('collection', 'CollectionController');
    Route::resource('user', 'UserController');

    Route::group(['prefix' => 'laravel-filemanager'], function () {
        \UniSharp\LaravelFilemanager\Lfm::routes();
    });
});

Route::group(['middleware' => ['auth', 'can:administer']], function () {
    Route::resource('featured-pieces', 'Admin\FeaturedPieceController');
    Route::resource('featured-artworks', 'Admin\FeaturedArtworkController');
    Route::resource('shuffled-items', 'Admin\ShuffledItemController');
    Route::get('harvests/launch/{id}', 'SpiceHarvesterController@launch');
    Route::get('harvests/harvestFailed/{id}', 'SpiceHarvesterController@harvestFailed');
    Route::get('harvests/orphaned/{id}', 'SpiceHarvesterController@orphaned');
    Route::get('harvests/{record_id}/refreshRecord/', 'SpiceHarvesterController@refreshRecord');
    Route::resource('harvests', 'SpiceHarvesterController');
    Route::get('item/backup', 'ItemController@backup');
    Route::get('item/geodata', 'ItemController@geodata');
    Route::post('item/refreshSelected', 'ItemController@refreshSelected');
    Route::get('item/reindex', 'ItemController@reindex');
    Route::get('authority/reindex', 'AuthorityController@reindex');
    Route::post('authority/destroySelected', 'AuthorityController@destroySelected');
    Route::get('authority/search', 'AuthorityController@search');
    Route::resource('authority', 'AuthorityController');
    Route::resource('sketchbook', 'SketchbookController');
    Route::resource('notices', 'NoticeController');
    Route::resource('redirects', 'RedirectController');
    Route::get('logs', '\Rap2hpoutre\LaravelLogViewer\LogViewerController@index');
});
