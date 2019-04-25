<?php

namespace App;

use Doctrine\ORM\Mapping as ORM;
use Monolith\Bundle\CMSBundle\Model\UserModel;

/**
 * @ORM\Entity
 * @ORM\Table(name="users",
 *      indexes={
 *          @ORM\Index(columns={"firstname"}),
 *          @ORM\Index(columns={"lastname"})
 *      }
 * )
 */
class User extends UserModel
{
}
