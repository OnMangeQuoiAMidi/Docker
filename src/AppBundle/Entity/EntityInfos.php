<?php

namespace AppBundle\Entity;


use Doctrine\ORM\Mapping as ORM;

/**
 * EntityInfos
 *
 * @ORM\MappedSuperclass
 * @ORM\HasLifecycleCallbacks
 */
class EntityInfos
{

    /**
     * @var datetime
     *
     * @ORM\Column(name="created_at", type="datetime")
     */
    private $created_at;

    /**
     * @var datetime
     *
     * @ORM\Column(name="modified_at", type="datetime")
     */
    private $modified_at;


    /**
     * @ORM\PrePersist()
     */
    public function prePersist() {
        $this->created_at = new \DateTime('now');
    }

    /**
     * @ORM\PreUpdate()
     */
    public function preUpdate() {
        $this->modified_at = new \DateTime('now');
    }

}