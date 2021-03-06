<?php
namespace STS\Core\Presentation;

use STS\Core\Api\DefaultPresentationFacade;
use STS\Core\Cacheable;
use STS\Core\ProfessionalGroup\MongoProfessionalGroupRepository;
use STS\Domain\Presentation;
use STS\Domain\ProfessionalGroup;
use STS\Domain\Survey;
use STS\Domain\Presentation\PresentationRepository;
use STS\Core\Member\MongoMemberRepository;
use STS\Core\School\MongoSchoolRepository;
use STS\Domain\Survey\Template;

class MongoPresentationRepository implements PresentationRepository
{
    private $mongoDb;
    private $cache;

    /**
     * __construct
     *
     * @param $mongoDb
     */
    public function __construct($mongoDb, Cacheable $cache)
    {
        $this->cache = $cache;
        $this->mongoDb = $mongoDb;
    }

    /**
     * save
     *
     * @param $presentation
     * @return Presentation
     * @throws \InvalidArgumentException
     */
    public function save($presentation)
    {
        if (!$presentation instanceof Presentation) {
            throw new \InvalidArgumentException('Instance of Presentation expected.');
        }
        if (is_null($presentation->getId())) {
            $presentation->markCreated();
        } else {
            $presentation->markUpdated();
        }
        $array = $presentation->toMongoArray();
        $id = array_shift($array);
        $array['date'] = new \MongoDate(strtotime($array['date']));
        $results = $this->mongoDb->presentation->update(
            array(
                '_id' => new \MongoId($id)
            ),
            $array,
            array(
                'upsert' => 1, 'safe' => 1
            )
        );
        if (array_key_exists('upserted', $results)) {
            /** @var \MongoId $id */
            $id = $results['upserted'];
            $presentation->setId($id->__toString());
        }
        return $presentation;
    }

    /**
     * load
     *
     * Load a single presentation
     *
     * @param $id
     * @return Presentation
     * @throws \InvalidArgumentException
     */
    public function load($id)
    {
        if (null !== $this->cache->getFromCache($id)) {
            return $this->cache->getFromCache($id);
        }

        $presentation = $this->loadFromMongo($id);
        return $presentation;
    }

    private function loadFromMongo($id) {
        $data = $this->mongoDb->presentation->findOne(
            array(
                '_id' => new \MongoId($id)
            )
        );
        if ($data == null) {
            throw new \InvalidArgumentException("Presentation not found with given id: $id");
        }
        $presentation = $this->mapData($data);
        return $presentation;
    }

    /**
     * @param array $criteria
     *
     * @return array
     */
    public function find($criteria = array())
    {
        $presentationData = $this->mongoDb->presentation->find($criteria)->sort(
            array(
                'date' => -1
            )
        );
        $presentations = array();
        if ($presentationData != null) {
            foreach ($presentationData as $data) {
                $presentations[] = $this->mapData($data);
            }
        }
        return $presentations;
    }

    /**
     * @param $data
     * @return Presentation
     */
    private function mapData($data)
    {
        /** @var \MongoId $id */
        $id = $data['_id'];
        $id = $id->__toString();
        if (null !== $this->cache->getFromCache($id)) {
            return $this->cache->getFromCache($id);
        }

        $presentation = new Presentation();
        $presentation->setId($id)
                     ->setNumberOfParticipants($data['nparticipants'])
                     ->setDate(date('Y-M-d h:i:s', $data['date']->sec))
                     ->setNotes($data['notes'])
                     ->setNumberOfFormsReturnedPost($data['nforms'])
                     ->setEnteredByUserId($data['entered_by_user_id'])
                     ->setType($data['type']);
        if (array_key_exists('nformspre', $data)) {
            $presentation->setNumberOfFormsReturnedPre($data['nformspre']);
        }
        if (array_key_exists('survey_id', $data)) {
            $template = new Template();
            $survey = $template->createSurveyInstance();
            $survey->setId($data['survey_id']);
            $presentation->setSurvey($survey);
        }

        // Handle legacy data when location could only be school
        if (! isset($data['location_class'])) {
            $data['location_id'] = $data['school_id'];
            $data['location_class'] = DefaultPresentationFacade::locationTypeSchool;
        }

        if (DefaultPresentationFacade::locationTypeSchool == $data['location_class']) {
            $schoolRepository = new MongoSchoolRepository($this->mongoDb, $this->cache);
            $location = $schoolRepository->load($data['location_id']);
        } else {
            $professionalGroupRepository = new MongoProfessionalGroupRepository($this->mongoDb);
            $location = $professionalGroupRepository->load($data['location_id']);
        }

        $presentation->setLocation($location);
        $memberRepository = new MongoMemberRepository($this->mongoDb, $this->cache);
        $members = array();
        foreach ($data['members'] as $memberId) {
            if ($memberId) {
                $members[] = $memberRepository->load($memberId);
            }
        }
        $presentation->setMembers($members);

        $this->cache->addToCache($id, $presentation);
        return $presentation;
    }

    /**
     * updateEnteredBy
     *
     * @param $old
     * @param $new
     */
    public function updateEnteredBy($old, $new)
    {
        $results = $this->mongoDb->presentation->update(
            array('entered_by_user_id' => $old),
            array('$set' => array('entered_by_user_id' => $new)),
            array(
                'multiple' => 1
            )
        );

        return $results;
    }

    public function delete($id)
    {
        $results = $this->mongoDb->presentation->remove(
            array('_id' => new \MongoId($id))
        );

        return ($results['n'] > 0);
    }
}
