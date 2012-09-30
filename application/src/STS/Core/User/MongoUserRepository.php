<?php
namespace STS\Core\User;
use STS\Domain\User;
use STS\Domain\User\UserRepository;

class MongoUserRepository implements UserRepository
{

    private $mongoDb;
    public function __construct($mongoDb)
    {
        $this->mongoDb = $mongoDb;
    }
    public function load($id)
    {
        $userData = $this->mongoDb->user->findOne(array(
                "_id" => $id
            ));
        if ($userData == null) {
            throw new \InvalidArgumentException("User not found with given id: $id");
        }
        return $this->mapData($userData);
    }

    public function find($criteria){
        $userData = $this->mongoDb->user->find($criteria);
        $users = array();
        if ($userData != null) {
            foreach ($userData as $data) {
                $users[] = $this->mapData($data);
            }
        }
        return $users;
    }

    private function mapData($userData)
    {
        $user = new User();
        $user->setId($userData['_id'])->setLegacyId($userData['legacyid'])->setEmail($userData['email'])
            ->setPassword($userData['pw'])->setSalt($userData['salt'])->setRole($userData['role'])
            ->setFirstName($userData['fname'])->setLastName($userData['lname']);
        if (array_key_exists('member_id', $userData)) {
            $user->setAssociatedMemberId($userData['member_id']['_id']->__toString());
        }
        return $user;
    }
}
