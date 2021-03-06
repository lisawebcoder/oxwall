<?php

class TextSearchManagerTest extends PHPUnit_Framework_TestCase
{
    /**
     * Tear down
     */
    protected function tearDown()
    {
        OW::getTextSearchManager()->deleteAllEntities();
    }

    /**
     * Test delete entities
     */
    public function testDeleteEntities()
    {
        $entities = [
            [
                'entity_type' => 'forum_post',
                'entity_id' => 1,
                'text' => 'forum post title',
                'tags' => [
                    'forum_post'
                ]
            ],
            [
                'entity_type' => 'forum_post',
                'entity_id' => 1,
                'text' => 'forum post body',
                'tags' => [
                    'forum_post'
                ]
            ]
        ];

        // add and delete test entities
        foreach ($entities as $entitiy)
        {
            OW::getTextSearchManager()->
                    addEntity($entitiy['entity_type'], $entitiy['entity_id'], $entitiy['text'], time(), $entitiy['tags']);

            OW::getTextSearchManager()->deleteEntity($entitiy['entity_type'], $entitiy['entity_id']);
        }

        // do we have entities?
        $entities = OW::getTextSearchManager()->getAllEntities(0, 2);
        $this->assertInternalType('array', $entities);
        $this->assertEquals(0, count($entities));
    }

    /**
     * Test deactivate all entities
     */
    public function testDeactivateAllEntities()
    {
        $entities = [
            [
                'entity_type' => 'forum_post',
                'entity_id' => 1,
                'text' => 'forum post',
                'tags' => [
                    'forum_post'
                ]
            ],
            [
                'entity_type' => 'forum_post',
                'entity_id' => 2,
                'text' => 'forum post',
                'tags' => [
                    'forum_post'
                ]
            ],
            [
                'entity_type' => 'forum_post',
                'entity_id' => 3,
                'text' => 'forum post',
                'tags' => [
                    'forum_post'
                ]
            ]
        ];

        // add test entities
        foreach ($entities as $entitiy)
        {
            OW::getTextSearchManager()->
                    addEntity($entitiy['entity_type'], $entitiy['entity_id'], $entitiy['text'],  time(), $entitiy['tags']);
        }

        // deactivate all forum post entities
        OW::getTextSearchManager()->deactivateAllEntities('forum_post');

        // search (we should get an empty result)
        $searchEntities = OW::getTextSearchManager()->searchEntities('forum', 0, 100);
        $this->assertInternalType('array', $searchEntities);
        $this->assertEquals(0, count($searchEntities));
        $this->assertEquals(0, OW::getTextSearchManager()->searchEntitiesCount('forum'));
    }

    /**
     * Test activate all entities
     */
    public function testActivateAllEntities()
    {
        $entities = [
            [
                'entity_type' => 'forum_post',
                'entity_id' => 1,
                'text' => 'forum post title',
                'tags' => [
                    'forum_post'
                ]
            ],
            [
                'entity_type' => 'forum_post',
                'entity_id' => 1,
                'text' => 'forum post body',
                'tags' => [
                    'forum_post'
                ]
            ],
            [
                'entity_type' => 'forum_topic',
                'entity_id' => 1,
                'text' => 'forum topic title',
                'tags' => [
                    'forum_topic'
                ]
            ]
        ];

        // add test entities
        foreach ($entities as $entitiy)
        {
            OW::getTextSearchManager()->
                    addEntity($entitiy['entity_type'], $entitiy['entity_id'], $entitiy['text'],  time(), $entitiy['tags']);
        }

        // deactivate all entities
        OW::getTextSearchManager()->deactivateAllEntities();

        // activate all entities
        OW::getTextSearchManager()->activateAllEntities();

        // get all entities
        $entities = OW::getTextSearchManager()->getAllEntities(0, 3);

        //  check entities status
        foreach ($entities as $entity)
        {
            $this->assertEquals(BASE_CLASS_AbstractSearchStorage::ENTITY_ACTIVATED, $entity['activated']);
        }
    }

    /**
     * Test search entities
     */
    public function testSearchEntities()
    {
        $entities = [
            [
                'entity_type' => 'forum_post',
                'entity_id' => 1,
                'text' => 'forum post title',
                'tags' => [
                    'forum_post'
                ],
                'active' => false
            ],
            [
                'entity_type' => 'forum_post',
                'entity_id' => 1,
                'text' => 'forum post body',
                'tags' => [
                    'forum_post'
                ],
                'active' => false
            ],
            [
                'entity_type' => 'forum_topic',
                'entity_id' => 1,
                'text' => 'forum topic title',
                'tags' => [
                    'forum_topic'
                ],
                'active' => true
            ]
        ];

        // add test entities
        foreach ($entities as $entitiy)
        {
            OW::getTextSearchManager()->
                    addEntity($entitiy['entity_type'], $entitiy['entity_id'], $entitiy['text'],  time(), $entitiy['tags']);

            // deactivate an entity
            if (!$entitiy['active']) {
                OW::getTextSearchManager()->
                        setEntitiesStatus($entitiy['entity_type'], $entitiy['entity_id'], BASE_CLASS_AbstractSearchStorage::ENTITY_STATUS_NOT_ACTIVE);
            }
        }

        // search only active entities
        $this->assertEquals(1, OW::getTextSearchManager()->searchEntitiesCount('forum'));
        $entities = OW::getTextSearchManager()->searchEntities('forum', 0, 100);

        // did we get forum topic?
        $this->assertInternalType('array', $entities);
        $this->assertEquals(1, count($entities));

        $currentEntity = array_shift($entities);
        $this->assertEquals('forum_topic', $currentEntity['entityType']);
        $this->assertEquals('1', $currentEntity['entityId']);

        // search an non existing entity
        $this->assertEquals(0, OW::getTextSearchManager()->searchEntitiesCount('non existing entity'));
        $entities = OW::getTextSearchManager()->searchEntities('non existing entity', 0, 100);
        $this->assertInternalType('array', $entities);
        $this->assertEquals(0, count($entities));
    }

    /**
     * Test search entities by tags
     */
    public function testSearchEntitiesByTags()
    {
        $entities = [
            [
                'entity_type' => 'forum_post',
                'entity_id' => 1,
                'text' => 'forum post title',
                'tags' => [
                    'forum_post'
                ]
            ],
            [
                'entity_type' => 'forum_post',
                'entity_id' => 1,
                'text' => 'forum post body',
                'tags' => [
                    'forum_post'
                ]
            ],
            [
                'entity_type' => 'forum_post',
                'entity_id' => 2,
                'text' => 'forum post title',
                'tags' => [
                    'forum_post'
                ]
            ],
            [
                'entity_type' => 'forum_post',
                'entity_id' => 2,
                'text' => 'forum post body',
                'tags' => [
                    'forum_post'
                ]
            ],
            [
                'entity_type' => 'forum_topic',
                'entity_id' => 1,
                'text' => 'forum topic title',
                'tags' => [
                    'forum_topic'
                ]
            ],
            [
                'entity_type' => 'forum_category',
                'entity_id' => 1,
                'text' => 'forum category title',
                'tags' => [
                    'forum_category'
                ]
            ]
        ];

        // add test entities
        foreach ($entities as $entitiy)
        {
            OW::getTextSearchManager()->
                    addEntity($entitiy['entity_type'], $entitiy['entity_id'], $entitiy['text'],  time(), $entitiy['tags']);
        }

        // search entities by tags
        $entities = OW::getTextSearchManager()->searchEntities('forum', 0, 100, [
            'forum_post'
        ]);

        // did we get only forum posts?
        $this->assertInternalType('array', $entities);
        $this->assertEquals(2, count($entities));

        foreach ($entities as $entity) 
        {
            $this->assertEquals('forum_post', $entity['entityType']);
        }
    }

    /**
     * Test set entities status
     */
    public function testSetEntitiesStatus()
    {
        $entities = [
            [
                'entity_type' => 'forum_post',
                'entity_id' => 1,
                'text' => 'forum post title',
                'tags' => [
                    'forum_post'
                ]
            ],
            [
                'entity_type' => 'forum_post',
                'entity_id' => 1,
                'text' => 'forum post body',
                'tags' => [
                    'forum_post'
                ]
            ]
        ];

        // add and inactivate test entities 
        foreach ($entities as $entitiy)
        {
            OW::getTextSearchManager()->
                    addEntity($entitiy['entity_type'], $entitiy['entity_id'], $entitiy['text'],  time(), $entitiy['tags']);

            // inactivate entities
            OW::getTextSearchManager()->
                    setEntitiesStatus($entitiy['entity_type'], $entitiy['entity_id'], BASE_CLASS_AbstractSearchStorage::ENTITY_STATUS_NOT_ACTIVE);
        }

        // get all entities
        $entities = OW::getTextSearchManager()->getAllEntities(0, 2);

        $this->assertInternalType('array', $entities);
        $this->assertEquals(2, count($entities));

        //  check entities status
        foreach ($entities as $entity)
        {
            $this->assertEquals(BASE_CLASS_AbstractSearchStorage::ENTITY_STATUS_NOT_ACTIVE, $entity['status']);
        }
    }

    /**
     * Test set entities status by tags
     */
    public function testSetEntitiesStatusByTags()
    {
        $entities = [
            [
                'entity_type' => 'forum_post',
                'entity_id' => 1,
                'text' => 'forum post title #1',
                'tags' => [
                    'tag_1'
                ]
            ],
            [
                'entity_type' => 'forum_post',
                'entity_id' => 2,
                'text' => 'forum post title #2',
                'tags' => [
                    'tag_2'
                ]
            ],
            [
                'entity_type' => 'forum_post',
                'entity_id' => 3,
                'text' => 'forum post title #3',
                'tags' => [
                    'tag_3'
                ]
            ]
        ];

        // add and inactivate test entities 
        foreach ($entities as $entitiy)
        {
            OW::getTextSearchManager()->
                    addEntity($entitiy['entity_type'], $entitiy['entity_id'], $entitiy['text'],  time(), $entitiy['tags']);

            // inactivate entities
            OW::getTextSearchManager()->setEntitiesStatusByTags([
                'tag_1',
                'tag_2',
                'tag_3'
            ], BASE_CLASS_AbstractSearchStorage::ENTITY_STATUS_NOT_ACTIVE);
        }

        // get all entities
        $entities = OW::getTextSearchManager()->getAllEntities(0, 3);

        $this->assertInternalType('array', $entities);
        $this->assertEquals(3, count($entities));

        // check entities status
        foreach ($entities as $entity)
        {
            $this->assertEquals(BASE_CLASS_AbstractSearchStorage::ENTITY_STATUS_NOT_ACTIVE, $entity['status']);
        }
    }

    /**
     * Test search entities by timestamp
     */
    public function testSearchEntitiesByTimestamp()
    {
        $daySeconds = 86400;
        $yesterday  = time() - $daySeconds;

        $testEntities = [
            [
                'entity_type' => 'forum_post',
                'entity_id' => 1,
                'text' => 'forum post title #1',
                'tags' => [
                ],
                'timestamp' => $yesterday
            ],
            [
                'entity_type' => 'forum_post',
                'entity_id' => 2,
                'text' => 'forum post title #2',
                'tags' => [
                ],
                'timestamp' => time()
            ],
            [
                'entity_type' => 'forum_post',
                'entity_id' => 3,
                'text' => 'forum post title #3',
                'tags' => [
                ],
                'timestamp' => $yesterday
            ],
            [
                'entity_type' => 'forum_post',
                'entity_id' => 4,
                'text' => 'forum post title #4',
                'tags' => [
                ],
                'timestamp' => $yesterday - $daySeconds //before yesterday
            ]
        ];

        // add test entities 
        foreach ($testEntities as $entitiy)
        {
            OW::getTextSearchManager()->addEntity($entitiy['entity_type'], 
                    $entitiy['entity_id'], $entitiy['text'],  $entitiy['timestamp'], $entitiy['tags']);
        }

        // search only entities that added yesterday
        $this->assertEquals(2, OW::getTextSearchManager()->
                searchEntitiesCount('forum post', [], $yesterday, $yesterday));

        $searchEntities = OW::getTextSearchManager()->
                searchEntities('forum post', 0, 100, [], BASE_CLASS_AbstractSearchStorage::SORT_BY_DATE, true, $yesterday, $yesterday);

        $this->assertInternalType('array', $searchEntities);
        $this->assertEquals(2, count($searchEntities));
    }

    /**
     * Test delete all entities by tags
     */
    public function testDeleteAllEntitiesByTags()
    {
        $testEntities = [
            [
                'entity_type' => 'forum_post',
                'entity_id' => 1,
                'text' => 'forum post title #1',
                'tags' => [
                    'tag_1'
                ]
            ],
            [
                'entity_type' => 'forum_post',
                'entity_id' => 2,
                'text' => 'forum post title #2',
                'tags' => [
                    'tag_2'
                ],
            ],
            [
                'entity_type' => 'forum_post',
                'entity_id' => 3,
                'text' => 'forum post title #3',
                'tags' => [
                    'tag_3'
                ]
            ],
            [
                'entity_type' => 'forum_post',
                'entity_id' => 4,
                'text' => 'forum post title #4',
                'tags' => [
                    'tag_3'
                ]
            ]
        ];

        // add test entities 
        foreach ($testEntities as $entitiy)
        {
            OW::getTextSearchManager()->addEntity($entitiy['entity_type'], 
                    $entitiy['entity_id'], $entitiy['text'],  time(), $entitiy['tags']);
        }

        // delete entities by tags
        OW::getTextSearchManager()->deleteAllEntitiesByTags(['tag_3', 'tag_2']);

        // we should find only a one entity
        $searchEntities = OW::getTextSearchManager()->searchEntities('forum post', 0, 100);
        $this->assertInternalType('array', $searchEntities);
        $this->assertEquals(1, count($searchEntities));
    }
}