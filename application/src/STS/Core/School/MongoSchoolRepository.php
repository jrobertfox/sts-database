<?php
namespace STS\Core\School;

use STS\Core\Cacheable;
use STS\Domain\Location\Address;
use STS\Domain\School;
use STS\Domain\School\SchoolRepository;
use STS\Core\Location\MongoAreaRepository;

class MongoSchoolRepository implements SchoolRepository
{
    private $mongoDb;
    /**
     * @var
     */
    private $cache;

    public function __construct($mongoDb, Cacheable $cache)
    {
        $this->mongoDb = $mongoDb;
        $this->cache = $cache;
    }

    /**
     * @param $id
     *
     * @return School
     */
    public function load($id)
    {
        if (null !== $this->cache->getFromCache($id)) {
            return $this->cache->getFromCache($id);
        }

        $school = $this->loadFromMongo($id);
        $this->cache->addToCache($id, $school);
        return $school;
    }

    private function loadFromMongo($id) {
        $schoolData = $this->mongoDb->school->findOne(
            array('_id' => new \MongoId($id))
        );
        if ($schoolData == null) {
            throw new \InvalidArgumentException("School not found with given id: $id");
        }
        $school = $this->mapData($schoolData);
        return $school;
    }

    public function find()
    {
        $schools = $this->mongoDb->school->find()->sort(
            array('name' => 1)
        );
        $returnData = array();
        foreach ($schools as $schoolData) {
            $returnData[strtolower($schoolData['name'])] = $this->mapData($schoolData);
        }
        ksort($returnData);
        return $returnData;
    }

    public function save($school)
    {
        if (!$school instanceof School) {
            throw new \InvalidArgumentException('Instance of School expected.');
        }

        if (is_null($school->getId())) {
            $school->markCreated();
        } else {
            $school->markUpdated();
        }

        $array = $school->toMongoArray();
        $id = array_shift($array);
        $results = $this->mongoDb->school
            ->update(
                array('_id' => new \MongoId($id)),
                $array,
                array('upsert' => 1, 'safe' => 1)
            );
        if (array_key_exists('upserted', $results)) {
            /** @var \MongoId $id */
            $id = $results['upserted'];
            $school->setId($id->__toString());
        }
        return $school;
    }

    /**
     * @param $schoolData
     *
     * @return School
     */
    private function mapData($schoolData)
    {
        $school = new School();
        /** @var \MongoId $id */
        $id = $schoolData['_id'];
        $school->setId($id->__toString())
               ->setLegacyId($schoolData['legacyid'])
               ->setName($schoolData['name']);
        if (array_key_exists('dateCreated', $schoolData)) {
            $school->setCreatedOn(strtotime(date('Y-M-d h:i:s', $schoolData['dateCreated']->sec)));
        }
        if (array_key_exists('dateUpdated', $schoolData)) {
            $school->setUpdatedOn(strtotime(date('Y-M-d h:i:s', $schoolData['dateUpdated']->sec)));
        }
        if (array_key_exists('area_id', $schoolData)) {
            $areaRepository = new MongoAreaRepository($this->mongoDb);
            $school->setArea($areaRepository->load($schoolData['area_id']['_id']));
        }
        if (array_key_exists('address', $schoolData)) {
            $address = new Address();
            // Handle legacy US-specific addresses
            if (is_array($schoolData['address'])) {
                if (array_key_exists('line_two', $schoolData['address'])) {
                    $address->setLineTwo($schoolData['address']['line_two']);
                }
                if (array_key_exists('zip', $schoolData['address'])) {
                    $address->setZip($schoolData['address']['zip']);
                }
                $address->setLineOne($schoolData['address']['line_one'])
                        ->setCity($schoolData['address']['city'])
                        ->setState($schoolData['address']['state']);
                $schoolData['address'] = $address->getAddress();
            }
            $address->setAddress($schoolData['address']);
            $school->setAddress($address);
        }
        if (array_key_exists('notes', $schoolData)) {
            $school->setNotes($schoolData['notes']);
        }
        if (array_key_exists('type', $schoolData)) {
            try {
                $school->setType($schoolData['type']);
            } catch (\InvalidArgumentException $e) {
                $school->setType(School::TYPE_OTHER);
            }
        }
        if (array_key_exists('is_inactive', $schoolData)) {
            $school->setIsInactive($schoolData['is_inactive']);
        }
        return $school;
    }
}
