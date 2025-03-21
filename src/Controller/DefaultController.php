<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

final class DefaultController extends AbstractController
{
    #[Route('/', name: 'app_default')]
    #[Route('/wallhaven', name: 'app_wallhaven')]
    public function wallhaven(Request $request): Response
    {


        $apiUrl = $this->processRequest($request);
        // Make the HTTP request to the Wallhaven API
        $client = HttpClient::create();
        $response = $client->request('GET', $apiUrl);

        // Parse the API response
        $responseData = $response->toArray();

        // Generate the XML schema
        $xmlSchema = $this->generateXmlSchema($responseData);

        // Return the XML response
        return new Response($xmlSchema, 200, [
            'Content-Type' => 'application/xml',
        ]);
    }

    #[Route('/debug', name: 'app_debug')]
    public function debug(Request $request): Response
    {
        $apiUrl = $this->processRequest($request);
        // Debug the constructed URL (optional)
        dump($apiUrl);

        // Make the HTTP request to the Wallhaven API
        $client = HttpClient::create();
        $response = $client->request('GET', $apiUrl);
        dump($response);
        $responseData = $response->toArray();
        dump($responseData);
        // dump($data);
        $xmlSchema = $this->generateXmlSchema($responseData);

        // dump($xmlSchema);
        return $this->render('default/debug.html.twig', [
            'xml_schema' => $xmlSchema,
        ]);
    }

    private function generateXmlSchema(array $responseData): string
    {
        // Create the root RSS element with the Media RSS namespace
        $xml = new \SimpleXMLElement('<rss version="2.0" xmlns:media="http://search.yahoo.com/mrss/"><channel></channel></rss>');

        // Initialize the title
        $title = '';

        // Check if 'meta' and 'query' exist and are not empty
        if (isset($responseData['meta']['query']) && !empty($responseData['meta']['query'])) {
            $title = is_array($responseData['meta']['query'])
                ? implode(', ', $responseData['meta']['query'])
                : $responseData['meta']['query'];
        }

        // Add channel metadata
        $channel = $xml->channel;
        $channel->addChild('title', 'Wallhaven Media RSS Feed - ' . $title);
        $channel->addChild('link', 'https://wallhaven.cc/');
        $channel->addChild('description', 'Media RSS feed from Wallhaven');

        // Add items to the channel
        foreach ($responseData['data'] as $item) {
            $mediaItem = $channel->addChild('item');
            $mediaItem->addChild('title', $item['id']);
            $mediaItem->addChild('link', $item['url']);
            $mediaItem->addChild('description', 'Image from Wallhaven');

            // Add the media:content tag with the required attributes
            $mediaContent = $mediaItem->addChild('media:content', null, 'http://search.yahoo.com/mrss/');
            $mediaContent->addAttribute('url', $item['path']);
            $mediaContent->addAttribute('type', $item['file_type']); // Use the file_type from the API response

            // Optionally add width and height attributes
            if (!empty($item['dimension_x']) && !empty($item['dimension_y'])) {
                $mediaContent->addAttribute('width', $item['dimension_x']);
                $mediaContent->addAttribute('height', $item['dimension_y']);
            }

            // Add media:title and media:description for better compatibility
            $mediaContent->addChild('media:title', $item['id'], 'http://search.yahoo.com/mrss/');
            $mediaContent->addChild('media:description', 'Image from Wallhaven', 'http://search.yahoo.com/mrss/');
        }

        return $xml->asXML();
    }

    private function processRequest(Request $request): string
    {
        // Get all query parameters from the request as an associative array
        $queryParams = $request->query->all();

        // Remove the 'page' parameter if it exists
        unset($queryParams['page']);

        // Add default 'categories' and 'purity' parameters only if they are not already set
        if (!isset($queryParams['categories'])) {
            $queryParams['categories'] = '111'; // Default categories
        }
        if (!isset($queryParams['purity'])) {
            $queryParams['purity'] = '111'; // Default purity
        }

        // Add the API key to the query parameters
        $queryParams['apikey'] = 'ZiBLWSqP1KePso5g6OWYYEzdjiEJgIda';

        // Build the query string for the Wallhaven API
        $queryString = http_build_query($queryParams);

        // Construct the full Wallhaven API URL
        return 'https://wallhaven.cc/api/v1/search?' . $queryString;
    }
}
