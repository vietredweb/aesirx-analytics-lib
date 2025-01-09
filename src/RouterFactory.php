<?php

/**
 * @package     AesirX_Analytics_Library
 *
 * @copyright   Copyright (C) 2016 - 2023 Aesir. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace AesirxAnalyticsLib;

use Pecee\Http\Middleware\IMiddleware;
use Pecee\Http\Request;
use Pecee\Http\Url;
use Pecee\SimpleRouter\Event\EventArgument;
use Pecee\SimpleRouter\Handlers\EventHandler;
use Pecee\SimpleRouter\Route\IGroupRoute;
use Pecee\SimpleRouter\Route\ILoadableRoute;
use Pecee\SimpleRouter\Route\RouteGroup;
use Pecee\SimpleRouter\Route\RouteUrl;
use Pecee\SimpleRouter\Router;

/**
 * @since 1.0.0
 */
class RouterFactory
{
    /**
     * @var callable
     */
    private $callback;

    private $router;

    private $uuidMatch = '[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}';

    private $requestBody;

    /**
     * @param callable $callback
     * @param IMiddleware $permissionCheckMiddleware
     * @param Url|null $url
     * @param string|null $basePath
     */
    public function __construct(
        callable $callback,
        IMiddleware $permissionCheckMiddleware,
        ?Url $url = null,
        ?string $basePath = null
    ) {
        $this->callback = $callback;
        $this->router = (new Router())
            ->setRenderMultipleRoutes(false);
        $this->requestBody = (array)json_decode(file_get_contents('php://input'), true);

        if (!empty($url)) {
            $this->router->getRequest()
                ->setUrl($url);
        }

        if (!empty($basePath)) {
            $this->router->addEventHandler(
                (new EventHandler())
                    ->register(EventHandler::EVENT_ADD_ROUTE, function (EventArgument $event) use ($basePath) {
                        // Skip routes added by group as these will inherit the url
                        if (!$event->__get('isSubRoute')) {
                            return;
                        }

                        $route = $event->__get('route');

                        switch (true) {
                            case $route instanceof ILoadableRoute:
                                $route->prependUrl($basePath);
                                break;
                            case $route instanceof IGroupRoute:
                                $route->prependPrefix($basePath);
                                break;
                        }
                    })
            );
        }

        $this->router->addRoute(
            (new RouteUrl('/wallet/v1/{network}/{address}/nonce', function (string $network, string $address) {
                return call_user_func(
                    $this->callback,
                    array_merge(
                        [
                            'wallet',
                            'v1',
                            'nonce',
                            '--network',
                            $network,
                            'network' => $network,
                            '--address',
                            $address,
                            'address' => $address,
                            '--domain',
                            $this->router->getRequest()->getHost(),
                            'domain' => $this->router->getRequest()->getHost(),
                        ],
                        $this->applyIfNotEmpty($this->requestBody, [
                            'text' => 'text',
                        ])
                    )
                );
            }))->setRequestMethods([Request::REQUEST_TYPE_POST])
        );

        $this->router->addRoute(
            (new RouteGroup())
                ->setSettings(['prefix' => '/consent/v1'])
                ->setCallback(
                    function () {
                        $this->router->addRoute(
                            (new RouteGroup())
                                ->setSettings(['prefix' => '/level1'])
                                ->setCallback(
                                    function () {
                                        $this->router->addRoute(
                                            (new RouteUrl(
                                                '/{uuid}/{consent}',
                                                function (string $uuid, string $consent) {
                                                    return call_user_func(
                                                        $this->callback,
                                                        array_merge (
                                                            [
                                                                'consent',
                                                                'level1',
                                                                'v1',
                                                                '--uuid',
                                                                $uuid,
                                                                'uuid' => $uuid,
                                                                '--consent',
                                                                $consent,
                                                                'consent' => $consent,
                                                                $this->requestBody['ip'] = empty($this->requestBody['ip']) ? $this->router->getRequest(
                                                                    )
                                                                        ->getIp() : $this->requestBody['ip']
                                                            ],
                                                            $this->applyIfNotEmpty($this->requestBody, [
                                                                'fingerprint' => 'fingerprint',
                                                                'user_agent' => 'user-agent',
                                                                'device' => 'device',
                                                                'browser_name' => 'browser-name',
                                                                'browser_version' => 'browser-version',
                                                                'lang' => 'lang',
                                                                'url' => 'url',
                                                                'referer' => 'referer',
                                                                'event_name' => 'event-name',
                                                                'event_type' => 'event-type',
                                                            ]),
                                                            $this->applyAttributes()
                                                        )
                                                    );
                                                }
                                            ))
                                                ->setRequestMethods([Request::REQUEST_TYPE_POST])
                                        );
                                        $this->router->addRoute(
                                            (new RouteUrl(
                                                '/revoke/{visitor_uuid}',
                                                function (string $visitor_uuid) {
                                                    return call_user_func($this->callback, [
                                                        'revoke',
                                                        'level1',
                                                        'v1',
                                                        '--visitor-uuid',
                                                        $visitor_uuid,
                                                        'visitor_uuid' => $visitor_uuid,
                                                    ]);
                                                }
                                            ))
                                                ->setWhere(['visitor_uuid' => $this->uuidMatch])
                                                ->setRequestMethods([Request::REQUEST_TYPE_PUT])
                                        );
                                    }
                                )
                        );

                        $this->router->addRoute(
                            (new RouteGroup())
                                ->setSettings(['prefix' => '/level2'])
                                ->setCallback(
                                    function () {
                                        $this->router->addRoute(
                                            (new RouteUrl('/list', function () {
                                                return call_user_func($this->callback, [
                                                    'list-consent',
                                                    'level2',
                                                    'v1',
                                                    '--token',
                                                    $this->getToken(),
                                                    'token' => $this->getToken(),
                                                ]);
                                            }))
                                                ->setRequestMethods([Request::REQUEST_TYPE_GET])
                                        );
                                        $this->router->addRoute(
                                            (new RouteUrl(
                                                '/revoke/{consent_uuid}',
                                                function (string $consent_uuid) {
                                                    return call_user_func($this->callback, [
                                                        'revoke',
                                                        'level2',
                                                        'v1',
                                                        '--consent-uuid',
                                                        $consent_uuid,
                                                        'consent_uuid' => $consent_uuid,
                                                        '--token',
                                                        $this->getToken(),
                                                        'token' => $this->getToken(),
                                                    ]);
                                                }
                                            ))
                                                ->setWhere(['consent_uuid' => $this->uuidMatch])
                                                ->setRequestMethods([Request::REQUEST_TYPE_PUT])
                                        );
                                        $this->router->addRoute(
                                            (new RouteUrl('/{uuid}', function (string $uuid) {
                                                return call_user_func(
                                                    $this->callback,
                                                    array_merge(
                                                        [
                                                            'consent',
                                                            'level2',
                                                            'v1',
                                                            '--uuid',
                                                            $uuid,
                                                            'visitor_uuid' => $uuid,
                                                            '--token',
                                                            $this->getToken(),
                                                            'token' => $this->getToken(),
                                                            $this->requestBody['ip'] = empty($this->requestBody['ip']) ? $this->router->getRequest(
                                                                )
                                                                    ->getIp() : $this->requestBody['ip']
                                                        ],
                                                        $this->applyIfNotEmpty(
                                                            $this->requestBody,
                                                            [
                                                                'consent' => 'consent',
                                                                'fingerprint' => 'fingerprint',
                                                                'user_agent' => 'user-agent',
                                                                'device' => 'device',
                                                                'browser_name' => 'browser-name',
                                                                'browser_version' => 'browser-version',
                                                                'lang' => 'lang',
                                                                'url' => 'url',
                                                                'referer' => 'referer',
                                                                'event_name' => 'event-name',
                                                                'event_type' => 'event-type',
                                                            ],
                                                            $this->applyAttributes()
                                                        )
                                                    )
                                                );
                                            }))
                                                ->setWhere(['uuid' => $this->uuidMatch])
                                                ->setRequestMethods([Request::REQUEST_TYPE_POST])
                                        );
                                    }
                                )
                        );

                        $this->router->addRoute(
                            (new RouteGroup())
                                ->setSettings(['prefix' => '/level3'])
                                ->setCallback(
                                    function () {
                                        $this->router->addRoute(
                                            (new RouteUrl(
                                                '/list/{network}/{wallet}',
                                                function (string $network, string $wallet) {
                                                    return call_user_func(
                                                        $this->callback,
                                                        array_merge(
                                                            [
                                                                'list-consent',
                                                                'level3',
                                                                'v1',
                                                                '--network',
                                                                $network,
                                                                'network' => $network,
                                                                '--wallet',
                                                                $wallet,
                                                                'wallet' => $wallet,
                                                            ],
                                                            $this->applyIfNotEmpty(
                                                                $this->router->getRequest()
                                                                    ->getUrl()
                                                                    ->getParams(),
                                                                ['signature' => 'signature']
                                                            )
                                                        )
                                                    );
                                                }
                                            ))
                                                ->setRequestMethods([Request::REQUEST_TYPE_GET])
                                        );
                                        $this->router->addRoute(
                                            (new RouteUrl(
                                                '/revoke/{consent_uuid}/{network}/{wallet}',
                                                function (
                                                    string $consent_uuid,
                                                    string $network,
                                                    string $wallet
                                                ) {
                                                    return call_user_func(
                                                        $this->callback,
                                                        array_merge(
                                                            [
                                                                'revoke',
                                                                'level3',
                                                                'v1',
                                                                '--consent-uuid',
                                                                $consent_uuid,
                                                                'consent_uuid' => $consent_uuid,
                                                                '--network',
                                                                $network,
                                                                'network' => $network,
                                                                '--wallet',
                                                                $wallet,
                                                                'wallet' => $wallet,
                                                            ],
                                                            $this->applyIfNotEmpty($this->requestBody, [
                                                                'signature' => 'signature',
                                                            ])
                                                        )
                                                    );
                                                }
                                            ))
                                                ->setWhere(['consent_uuid' => $this->uuidMatch])
                                                ->setRequestMethods([Request::REQUEST_TYPE_PUT])
                                        );
                                        $this->router->addRoute(
                                            (new RouteUrl(
                                                '/{uuid}/{network}/{wallet}',
                                                function (string $uuid, string $network, string $wallet) {
                                                    return call_user_func(
                                                        $this->callback,
                                                        array_merge(
                                                            [
                                                                'consent',
                                                                'level3',
                                                                'v1',
                                                                '--visitor-uuid',
                                                                $uuid,
                                                                'visitor_uuid' => $uuid,
                                                                '--network',
                                                                $network,
                                                                'network' => $network,
                                                                '--wallet',
                                                                $wallet,
                                                                'wallet' => $wallet,
                                                                $this->requestBody['ip'] = empty($this->requestBody['ip']) ? $this->router->getRequest(
                                                                    )
                                                                        ->getIp() : $this->requestBody['ip']
                                                            ],
                                                            $this->applyIfNotEmpty($this->requestBody, [
                                                                'consent' => 'consent',
                                                                'signature' => 'signature',
                                                                'fingerprint' => 'fingerprint',
                                                                'user_agent' => 'user-agent',
                                                                'device' => 'device',
                                                                'browser_name' => 'browser-name',
                                                                'browser_version' => 'browser-version',
                                                                'lang' => 'lang',
                                                                'url' => 'url',
                                                                'referer' => 'referer',
                                                                'event_name' => 'event-name',
                                                                'event_type' => 'event-type',
                                                            ]),
                                                            $this->applyAttributes()
                                                        )
                                                    );
                                                }
                                            ))
                                                ->setWhere(['uuid' => $this->uuidMatch])
                                                ->setRequestMethods([Request::REQUEST_TYPE_POST])
                                        );
                                    }
                                )
                        );

                        $this->router->addRoute(
                            (new RouteGroup())
                                ->setSettings(['prefix' => '/level4'])
                                ->setCallback(
                                    function () {
                                        $this->router->addRoute(
                                            (new RouteUrl(
                                                '/list/{network}/{web3id}/{wallet}',
                                                function (string $network, string $web3id, string $wallet) {
                                                    return call_user_func(
                                                        $this->callback,
                                                        array_merge(
                                                            [
                                                                'list-consent',
                                                                'level4',
                                                                'v1',
                                                                '--network',
                                                                $network,
                                                                'network' => $network,
                                                                '--wallet',
                                                                $wallet,
                                                                'wallet' => $wallet,
                                                                '--web3id',
                                                                $web3id,
                                                                'web3id' => $web3id,
                                                            ],
                                                            $this->applyIfNotEmpty(
                                                                $this->router->getRequest()
                                                                    ->getUrl()
                                                                    ->getParams(),
                                                                [
                                                                    'signature' => 'signature',
                                                                ]
                                                            )
                                                        )
                                                    );
                                                }
                                            ))
                                                ->setWhere([
                                                    'web3id' => '[\@\w-]+',
                                                ])
                                                ->setRequestMethods([Request::REQUEST_TYPE_GET])
                                        );
                                        $this->router->addRoute(
                                            (new RouteUrl(
                                                '/revoke/{consent_uuid}/{network}/{web3id}/{wallet}',
                                                function (
                                                    string $consent_uuid,
                                                    string $network,
                                                    string $web3id,
                                                    string $wallet
                                                ) {
                                                    return call_user_func(
                                                        $this->callback,
                                                        array_merge(
                                                            [
                                                                'revoke',
                                                                'level4',
                                                                'v1',
                                                                '--consent-uuid',
                                                                $consent_uuid,
                                                                'consent_uuid' => $consent_uuid,
                                                                '--network',
                                                                $network,
                                                                'network' => $network,
                                                                '--wallet',
                                                                $wallet,
                                                                'wallet' => $wallet,
                                                                '--web3id',
                                                                $web3id,
                                                                'web3id' => $web3id,
                                                            ],
                                                            $this->applyIfNotEmpty($this->requestBody, [
                                                                'signature' => 'signature',
                                                            ])
                                                        )
                                                    );
                                                }
                                            ))
                                                ->setWhere([
                                                    'consent_uuid' => $this->uuidMatch,
                                                    'web3id' => '[\@\w-]+',
                                                ])
                                                ->setRequestMethods([Request::REQUEST_TYPE_PUT])
                                        );
                                        $this->router->addRoute(
                                            (new RouteUrl(
                                                '/{uuid}/{network}/{web3id}/{wallet}',
                                                function (
                                                    string $uuid,
                                                    string $network,
                                                    string $web3id,
                                                    string $wallet
                                                ) {
                                                    return call_user_func(
                                                        $this->callback,
                                                        array_merge(
                                                            [
                                                                'consent',
                                                                'level4',
                                                                'v1',
                                                                '--visitor-uuid',
                                                                $uuid,
                                                                'visitor_uuid' => $uuid,
                                                                '--network',
                                                                $network,
                                                                'network' => $network,
                                                                '--wallet',
                                                                $wallet,
                                                                'wallet' => $wallet,
                                                                '--web3id',
                                                                $web3id,
                                                                'web3id' => $web3id,
                                                                $this->requestBody['ip'] = empty($this->requestBody['ip']) ? $this->router->getRequest(
                                                                    )
                                                                        ->getIp() : $this->requestBody['ip']
                                                            ],
                                                            $this->applyIfNotEmpty($this->requestBody, [
                                                                'consent' => 'consent',
                                                                'signature' => 'signature',
                                                                'fingerprint' => 'fingerprint',
                                                                'user_agent' => 'user-agent',
                                                                'device' => 'device',
                                                                'browser_name' => 'browser-name',
                                                                'browser_version' => 'browser-version',
                                                                'lang' => 'lang',
                                                                'url' => 'url',
                                                                'referer' => 'referer',
                                                                'event_name' => 'event-name',
                                                                'event_type' => 'event-type',
                                                            ]),
                                                            $this->applyAttributes()
                                                        )
                                                    );
                                                }
                                            ))
                                                ->setWhere([
                                                    'uuid' => $this->uuidMatch,
                                                    'web3id' => '[\@\w-]+',
                                                ])
                                                ->setRequestMethods([Request::REQUEST_TYPE_POST])
                                        );
                                    }
                                )
                        );
                    }
                )
        );

        $this->router->addRoute(
            (new RouteGroup())
                ->setSettings(['prefix' => '/consent/v2/level4'])
                ->setCallback(
                    function () {
                        $this->router->addRoute(
                            (new RouteUrl(
                                '/list/{network}/{wallet}',
                                function (string $network, string $wallet) {
                                    return call_user_func(
                                        $this->callback,
                                        array_merge(
                                            [
                                                'list-consent',
                                                'level4',
                                                'v2',
                                                '--network',
                                                $network,
                                                'network' => $network,
                                                '--wallet',
                                                $wallet,
                                                'wallet' => $wallet,
                                                '--web3id',
                                                $this->getToken(),
                                                'token' => $this->getToken(),
                                            ],
                                            $this->applyIfNotEmpty(
                                                $this->router->getRequest()
                                                    ->getUrl()
                                                    ->getParams(),
                                                [
                                                    'signature' => 'signature',
                                                ]
                                            )
                                        )
                                    );
                                }
                            ))
                                ->setRequestMethods([Request::REQUEST_TYPE_GET])
                        );
                        $this->router->addRoute(
                            (new RouteUrl(
                                '/revoke/{consent_uuid}/{network}/{wallet}',
                                function (
                                    string $consent_uuid,
                                    string $network,
                                    string $wallet
                                ) {
                                    return call_user_func(
                                        $this->callback,
                                        array_merge(
                                            [
                                                'revoke',
                                                'level4',
                                                'v2',
                                                '--consent-uuid',
                                                $consent_uuid,
                                                'consent_uuid' => $consent_uuid,
                                                '--network',
                                                $network,
                                                'network' => $network,
                                                '--wallet',
                                                $wallet,
                                                'wallet' => $wallet,
                                                '--web3id',
                                                $this->getToken(),
                                                'token' => $this->getToken(),
                                            ],
                                            $this->applyIfNotEmpty($this->requestBody, [
                                                'signature' => 'signature',
                                            ])
                                        )
                                    );
                                }
                            ))
                                ->setWhere([
                                    'consent_uuid' => $this->uuidMatch,
                                ])
                                ->setRequestMethods([Request::REQUEST_TYPE_PUT])
                        );
                        $this->router->addRoute(
                            (new RouteUrl(
                                '/{uuid}/{network}/{wallet}',
                                function (
                                    string $uuid,
                                    string $network,
                                    string $wallet
                                ) {
                                    return call_user_func(
                                        $this->callback,
                                        array_merge(
                                            [
                                                'consent',
                                                'level4',
                                                'v2',
                                                '--visitor-uuid',
                                                $uuid,
                                                'visitor_uuid' => $uuid,
                                                '--network',
                                                $network,
                                                'network' => $network,
                                                '--wallet',
                                                $wallet,
                                                'wallet' => $wallet,
                                                '--web3id',
                                                $this->getToken(),
                                                'token' => $this->getToken(),
                                                $this->requestBody['ip'] = empty($this->requestBody['ip']) ? $this->router->getRequest(
                                                    )
                                                        ->getIp() : $this->requestBody['ip']
                                            ],
                                            $this->applyIfNotEmpty($this->requestBody, [
                                                'consent' => 'consent',
                                                'signature' => 'signature',
                                                'fingerprint' => 'fingerprint',
                                                'user_agent' => 'user-agent',
                                                'device' => 'device',
                                                'browser_name' => 'browser-name',
                                                'browser_version' => 'browser-version',
                                                'lang' => 'lang',
                                                'url' => 'url',
                                                'referer' => 'referer',
                                                'event_name' => 'event-name',
                                                'event_type' => 'event-type',
                                            ])
                                        )
                                    );
                                }
                            ))
                                ->setWhere([
                                    'uuid' => $this->uuidMatch,
                                ])
                                ->setRequestMethods([Request::REQUEST_TYPE_POST])
                        );
                    }
                )
        );

        $this->router->addRoute(
            (new RouteGroup())
                ->setSettings(['prefix' => '/visitor'])
                ->setCallback(
                    function () {
                        $this->router->addRoute(
                            (new RouteGroup())
                                ->setSettings(['prefix' => '/v1'])
                                ->setCallback(
                                    function () {
                                        $this->router->addRoute(
                                            (new RouteUrl('/init', function () {
                                                return call_user_func(
                                                    $this->callback,
                                                    array_merge(
                                                        [
                                                            'visitor',
                                                            'init',
                                                            'v1',
                                                            '--ip',
                                                            $this->requestBody['ip'] = empty($this->requestBody['ip']) ? $this->router->getRequest(
                                                            )
                                                                ->getIp() : $this->requestBody['ip'],
                                                        ],
                                                        $this->applyIfNotEmpty($this->requestBody, [
                                                            'user_agent' => 'user-agent',
                                                            'device' => 'device',
                                                            'browser_name' => 'browser-name',
                                                            'browser_version' => 'browser-version',
                                                            'lang' => 'lang',
                                                            'url' => 'url',
                                                            'referer' => 'referer',
                                                            'event_name' => 'event-name',
                                                            'event_type' => 'event-type',
                                                        ]),
                                                        $this->applyAttributes()
                                                    )
                                                );
                                            }))
                                                ->setRequestMethods([Request::REQUEST_TYPE_POST])
                                        );
                                        $this->router->addRoute(
                                            (new RouteUrl('/start', function () {
                                                return call_user_func(
                                                    $this->callback,
                                                    array_merge(
                                                        ['visitor', 'start', 'v1'],
                                                        $this->applyIfNotEmpty($this->requestBody, [
                                                            'visitor_uuid' => 'visitor-uuid',
                                                            'url' => 'url',
                                                            'referer' => 'referer',
                                                            'event_name' => 'event-name',
                                                            'event_type' => 'event-type',
                                                            'event_uuid' => 'event-uuid',
                                                        ]),
                                                        $this->applyAttributes()
                                                    )
                                                );
                                            }))
                                                ->setRequestMethods([Request::REQUEST_TYPE_POST])
                                        );
                                        $this->router->addRoute(
                                            (new RouteUrl('/end', function () {
                                                return call_user_func(
                                                    $this->callback,
                                                    array_merge(
                                                        ['visitor', 'end', 'v1'],
                                                        $this->applyIfNotEmpty($this->requestBody, [
                                                            'visitor_uuid' => 'visitor-uuid',
                                                            'event_uuid' => 'event-uuid',
                                                        ])
                                                    )
                                                );
                                            }))
                                                ->setRequestMethods([Request::REQUEST_TYPE_POST])
                                        );
                                        $this->router->addRoute(
                                            (new RouteUrl('/{uuid}', function (string $uuid) {
                                                return call_user_func($this->callback, [
                                                    'get',
                                                    'visitor',
                                                    'v1',
                                                    '--uuid',
                                                    $uuid,
                                                    'uuid' => $uuid,
                                                ]);
                                            }))
                                                ->setWhere(['uuid' => $this->uuidMatch])
                                                ->setRequestMethods([Request::REQUEST_TYPE_GET])
                                        );
                                    }
                                )
                        );
                        $this->router->addRoute(
                            (new RouteGroup())
                                ->setSettings(['prefix' => '/v2'])
                                ->setCallback(
                                    function () {
                                        $this->router->addRoute(
                                            (new RouteUrl('/start', function () {
                                                return call_user_func(
                                                    $this->callback,
                                                    array_merge(
                                                        [
                                                            'visitor',
                                                            'start',
                                                            'v2',
                                                            '--ip',
                                                            $this->requestBody['ip'] = empty($this->requestBody['ip']) ? $this->router->getRequest(
                                                            )
                                                                ->getIp() : $this->requestBody['ip'],
                                                        ],
                                                        $this->applyIfNotEmpty($this->requestBody, [
                                                            'fingerprint' => 'fingerprint',
                                                            'user_agent' => 'user-agent',
                                                            'device' => 'device',
                                                            'browser_name' => 'browser-name',
                                                            'browser_version' => 'browser-version',
                                                            'lang' => 'lang',
                                                            'url' => 'url',
                                                            'referer' => 'referer',
                                                            'event_name' => 'event-name',
                                                            'event_type' => 'event-type',
                                                        ]),
                                                        $this->applyAttributes()
                                                    )
                                                );
                                            }))
                                                ->setRequestMethods([Request::REQUEST_TYPE_POST])
                                        );
                                    }
                                )
                        );
                    }
                )
        );

        $this->router->addRoute(
            (new RouteGroup())
                ->setSettings(['middleware' => [$permissionCheckMiddleware]])
                ->setCallback(
                    function () {
                        $this->router->addRoute(
                            (new RouteUrl(
                                '/consents/v1/{start_date}/{end_date}/date',
                                function (string $start, string $end) {
                                    return call_user_func(
                                        $this->callback,
                                        array_merge(
                                            [
                                                'list-consent-statistics',
                                                'total-consents-by-date',
                                                'v1',
                                                '--start',
                                                $start,
                                                '--end',
                                                $end
                                            ],
                                            $this->applyListParams($start, $end)
                                        )
                                    );
                                }
                            ))
                                ->setRequestMethods([Request::REQUEST_TYPE_GET])
                        );
                        $this->router->addRoute(
                            (new RouteUrl(
                                '/consents/v1/{start_date}/{end_date}/tier',
                                function (string $start, string $end) {
                                    return call_user_func(
                                        $this->callback,
                                        array_merge(
                                            [
                                                'list-consent-statistics',
                                                'total-tiers-by-date',
                                                'v1',
                                                '--start',
                                                $start,
                                                '--end',
                                                $end
                                            ],
                                            $this->applyListParams($start, $end)
                                        )
                                    );
                                }
                            ))
                                ->setRequestMethods([Request::REQUEST_TYPE_GET])
                        );
                        $this->router->addRoute(
                            (new RouteUrl(
                                '/consents/v1/{start_date}/{end_date}',
                                function (string $start, string $end) {
                                    return call_user_func(
                                        $this->callback,
                                        array_merge(
                                            [
                                                'list-consent-statistics',
                                                'all',
                                                'v1',
                                                '--start',
                                                $start,
                                                '--end',
                                                $end
                                            ],
                                            $this->applyListParams($start, $end)
                                        )
                                    );
                                }
                            ))
                                ->setRequestMethods([Request::REQUEST_TYPE_GET])
                        );
                        $this->router->addRoute(
                            (new RouteUrl('/flow/v1/{flow_uuid}', function (string $flowUuid) {
                                return call_user_func(
                                    $this->callback,
                                    array_merge(
                                        ['get', 'flow', 'v1', 'flow_uuid' => $flowUuid],
                                        $this->applyIfNotEmpty(
                                            $this->router->getRequest()
                                                ->getUrl()
                                                ->getParams(),
                                            ['with' => 'with']
                                        )
                                    )
                                );
                            }))
                                ->setWhere(['flow_uuid' => $this->uuidMatch])
                                ->setRequestMethods([Request::REQUEST_TYPE_GET])
                        );
                        $this->router->addRoute(
                            (new RouteUrl('/flow/v1/{start_date}/{end_date}', function (string $start, string $end) {
                                return call_user_func(
                                    $this->callback,
                                    array_merge(
                                        ['get', 'flows', 'v1', '--start', $start, '--end', $end],
                                        $this->applyListParams($start, $end)
                                    )
                                );
                            }))
                                ->setRequestMethods([Request::REQUEST_TYPE_GET])
                        );
                        $this->router->addRoute(
                            (new RouteUrl('/flow/v1/{start_date}/{end_date}/date', function (string $start, string $end) {
                                return call_user_func(
                                    $this->callback,
                                    array_merge(
                                        ['get', 'flows-date', 'v1', '--start', $start, '--end', $end],
                                        $this->applyListParams($start, $end)
                                    )
                                );
                            }))
                                ->setRequestMethods([Request::REQUEST_TYPE_GET])
                        );
                        $this->router->addRoute(
                            (new RouteUrl('/visitor/v1/{start_date}/{end_date}', function (string $start, string $end) {
                                return call_user_func(
                                    $this->callback,
                                    array_merge(
                                        ['get', 'events', 'v1', '--start', $start, '--end', $end],
                                        $this->applyListParams($start, $end)
                                    )
                                );
                            }))
                                ->setRequestMethods([Request::REQUEST_TYPE_GET])
                        );
                        $this->router->addRoute(
                            (new RouteUrl('/live_visitors/list', function () {
                                return call_user_func(
                                    $this->callback,
                                    array_merge(
                                        [
                                            'live-visitors',
                                            'list',
                                        ],
                                        $this->applyListParams()
                                    )
                                );
                            }))
                                ->setRequestMethods([Request::REQUEST_TYPE_GET])
                        );
                        $this->router->addRoute(
                            (new RouteUrl('/live_visitors/total', function () {
                                return call_user_func(
                                    $this->callback,
                                    array_merge(
                                        [
                                            'live-visitors',
                                            'total',
                                        ],
                                        $this->applyListParams()
                                    )
                                );
                            }))
                                ->setRequestMethods([Request::REQUEST_TYPE_GET])
                        );
                        $this->router->addRoute(
                            (new RouteUrl('/live_visitors/device', function () {
                                return call_user_func(
                                    $this->callback,
                                    array_merge(
                                        [
                                            'live-visitors',
                                            'device',
                                        ],
                                        $this->applyListParams()
                                    )
                                );
                            }))
                                ->setRequestMethods([Request::REQUEST_TYPE_GET])
                        );
                        foreach (
                            [
                                'visits',
                                'domains',
                                'metrics',
                                'pages',
                                'visitors',
                                'browsers',
                                'browserversions',
                                'languages',
                                'devices',
                                'countries',
                                'cities',
                                'isps',
                                'attribute',
                                'events',
                                'events-name-type',
                                'attribute-date',
                                'user-types',
                                'outlinks',
                                'channels',
                                'user-flows',
                                'regions',
                                'referrers',
                            ] as $statistic
                        ) {
                            $this->router->addRoute(
                                (new RouteUrl(
                                    '/' . str_replace('-', '_', $statistic) . '/v1/{start_date}/{end_date}',
                                    function (string $start, string $end) use ($statistic) {
                                        return call_user_func(
                                            $this->callback,
                                            array_merge(
                                                [
                                                    'statistics',
                                                    $statistic == 'attribute' ? 'attributes' : $statistic,
                                                    'v1',
                                                    '--start',
                                                    $start,
                                                    '--end',
                                                    $end,
                                                ],
                                                $this->applyListParams($start, $end)
                                            )
                                        );
                                    }
                                ))
                                    ->setRequestMethods([Request::REQUEST_TYPE_GET])
                            );
                        }
                    }
                )
        );

        $this->router->addRoute(
            (new RouteGroup())
                ->setSettings([
                    'prefix' => '/conversion/v1',
                    'middleware' => [$permissionCheckMiddleware],
                ])
                ->setCallback(
                    function () {
                        $this->router->addRoute(
                            (new RouteUrl(
                                '/products/{start_date}/{end_date}',
                                function (string $start, string $end) {
                                    return call_user_func(
                                        $this->callback,
                                        array_merge(
                                            [
                                                'conversion',
                                                'products',
                                                'v1',
                                                '--start',
                                                "start" => $start,
                                                $start,
                                                '--end',
                                                "end" => $end,
                                                $end
                                            ],
                                            $this->applyListParams($start, $end)
                                        )
                                    );
                                }
                            ))
                                ->setRequestMethods([Request::REQUEST_TYPE_GET])
                        );
                        $this->router->addRoute(
                            (new RouteUrl(
                                '/products-chart/{start_date}/{end_date}',
                                function (string $start, string $end) {
                                    return call_user_func(
                                        $this->callback,
                                        array_merge(
                                            [
                                                'conversion',
                                                'products-chart',
                                                'v1',
                                                '--start',
                                                $start,
                                                '--end',
                                                $end
                                            ],
                                            $this->applyListParams($start, $end)
                                        )
                                    );
                                }
                            ))
                                ->setRequestMethods([Request::REQUEST_TYPE_GET])
                        );
                        $this->router->addRoute(
                            (new RouteUrl(
                                '/statistics/{start_date}/{end_date}',
                                function (string $start, string $end) {
                                    return call_user_func(
                                        $this->callback,
                                        array_merge(
                                            [
                                                'conversion',
                                                'statistics',
                                                'v1',
                                                '--start',
                                                $start,
                                                '--end',
                                                $end
                                            ],
                                            $this->applyListParams($start, $end)
                                        )
                                    );
                                }
                            ))
                                ->setRequestMethods([Request::REQUEST_TYPE_GET])
                        );
                        $this->router->addRoute(
                            (new RouteUrl(
                                '/statistics-chart/{start_date}/{end_date}',
                                function (string $start, string $end) {
                                    return call_user_func(
                                        $this->callback,
                                        array_merge(
                                            [
                                                'conversion',
                                                'statistics-chart',
                                                'v1',
                                                '--start',
                                                $start,
                                                '--end',
                                                $end
                                            ],
                                            $this->applyListParams($start, $end)
                                        )
                                    );
                                }
                            ))
                                ->setRequestMethods([Request::REQUEST_TYPE_GET])
                        );
                    }
                )
        );

        $this->router->addRoute(
            (new RouteUrl('/datastream/template/joomla4.demo.analytics.aesirx.io', function () {
                return call_user_func(
                    $this->callback,
                    array_merge(
                        [
                            'datastream',
                            'template',
                            'joomla4.demo.analytics.aesirx.io',
                        ],
                    )
                );
            }))->setRequestMethods([Request::REQUEST_TYPE_GET])
        );

        $this->router->addRoute(
            (new RouteUrl('/datastream/template', function () {
                return call_user_func(
                    $this->callback,
                    array_merge(
                        [
                            'datastream',
                            'template',
                        ],
                        $_POST
                    )
                );
            }))->setRequestMethods([Request::REQUEST_TYPE_POST])
        );

        
    }

    private function getToken(): string
    {
        $auth = $this->router->getRequest()->getHeader('authorization', '');
        $matches = [];

        if (preg_match('/Bearer\s(\S+)/', $auth, $matches)) {
            return $matches[1];
        }

        return '';
    }

    private function applyIfNotEmpty(array $request, array $fields): array
    {
        $command['request'] = $request;
        $command['fields'] = $fields;

        foreach ($fields as $from => $to) {
            if (array_key_exists($from, $request)) {
                foreach ((array)$request[$from] as $one) {
                    $command[] = '--' . $to;
                    $command[] = $one;
                }
            }
        }

        return $command;
    }

    private function applyListParams($start = null, $end = null): array
    {
        $command = $this->router->getRequest()->getUrl()->getParams();

        if ($start) {
            $command['filter']['start'] = $start;
        }

        if ($end) {
            $command['filter']['end'] = $end;
        }

        foreach (
            $this->router->getRequest()
                ->getUrl()
                ->getParams() as $key => $values
        ) {
            $converterKey = str_replace('_', '-', $key);

            switch ($key) {
                case 'page':
                case 'page_size':
                    $command[] = '--' . $converterKey;
                    $command[] = $values;
                    break;
                case 'sort':
                case 'with':
                case 'sort_direction':
                    foreach ($values as $value) {
                        $command[] = '--' . $converterKey;
                        $command[] = $value;
                    }
                    break;
                case 'filter':
                case 'filter_not':
                    foreach ($values as $keyValue => $value) {
                        if (is_iterable($value)) {
                            foreach ($value as $v) {
                                $command[] = '--' . $converterKey;
                                $command[] = $keyValue . '[]=' . $v;
                            }
                        } else {
                            $command[] = '--' . $converterKey;
                            $command[] = $keyValue . '=' . $value;
                        }
                    }

                    break;
            }
        }

        return $command;
    }

    private function applyAttributes(): array
    {
        $command = [];

        if (!empty($this->requestBody['attributes'] ?? [])) {
            $command[] = $this->requestBody['attributes'];
            foreach ($this->requestBody['attributes'] as $key => $attribute) {
                $command[] = '--attributes';
                $command[] = $attribute['name'] . '=' . $attribute['value'];
            }
        }

        return $command;
    }

    public function getSimpleRouter(): Router
    {
        return $this->router;
    }
}
