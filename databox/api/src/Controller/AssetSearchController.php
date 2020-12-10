<?php

declare(strict_types=1);

namespace App\Controller;

use App\Elasticsearch\AssetSearch;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(name="asset_search_", path="/assets")
 */
class AssetSearchController extends AbstractController
{
    /**
     * @Route(path="/search", name="search")
     */
    public function searchAction(Request $request, AssetSearch $search)
    {
        $data = $search->search(
            $request->get('q', ''),
            'my_user',
            ['g1', 'group_2'],
            [
//                'tags_must_not' => [
//                    'online'
//                ]
            ]
        );

        dump($data);

        return new Response('');
    }
}
