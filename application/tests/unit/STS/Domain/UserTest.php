<?php
use STS\TestUtilities\UserTestCase;
use STS\Domain\User;

class UserTest extends UserTestCase
{
    /**
     * @test
     */
    public function createValidObject()
    {
        $user = $this->getValidUser();
        $this->assertValidUser($user);
    }

    /**
     * @test
     */
    public function validInitializePassword(){
        $temp = 'abcd1234';
        $user = new User();
        $user->initializePassword($temp);
        $salt = $user->getSalt();
        $password = $user->getPassword();
        $this->assertRegExp('/^[a-z0-9]{32}$/i', $salt);
        $this->assertRegExp('/^[a-z0-9]{40}$/i',$password);
        $this->assertTrue(sha1($salt.$temp) == $password);
    }

    /**
     * @test
     */
    public function getValidMongoArray(){
        $user = $this->getValidUser();

        $validArray = array(
            '_id'=> self::BASIC_USER_NAME, 
            'email'=> self::BASIC_USER_EMAIL, 
            'fname'=> self::VALID_FIRST_NAME, 
            'lname'=>self::VALID_LAST_NAME,
            'legacyid'=> self:: VALID_LEGACY_ID, 
            'role' => self::BASIC_USER_ROLE, 
            'pw' => self::PASSWORD, 
            'salt'=> self::SALT, 
            'member_id'=>array(
                "_id"=> new \MongoId(self::ASSOCIATED_MEMBER_ID)
                )
            );
        
        $this->assertEquals($validArray, $user->toMongoArray());
    }
 
}
