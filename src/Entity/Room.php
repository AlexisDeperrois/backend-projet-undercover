<?php

namespace App\Entity;

use App\Repository\RoomRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass=RoomRepository::class)
 */
class Room
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     * @Groups("get_room_data")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     * @Groups("get_room_data")
     */
    private $room_code;

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @Groups("get_room_data")
     * @Assert\Range(
     *          min=3,
     *          max=15,
     *          notInRangeMessage = "le nombre de joueurs doit être compris entre 3 et 15.",
     *          )
     */
    private $nb_player;

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @Assert\Range(
     *          min=1,
     *          max=15,
     *          notInRangeMessage = "nombre d'imposteurs incorrect",
     *          )
     * @Groups("get_room_data")
     */
    private $nb_agent;

    /**
     * @ORM\Column(type="boolean")
     * @Groups("get_room_data")
     */
    private $asChrono;
    
     /**
     * @ORM\Column(type="integer", nullable=true)
     * @Groups("get_room_data")
     */
    private $Duration;

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="rooms")
     * @ORM\JoinColumn(nullable=false)
     * @Groups("get_room_data")
     */
    private $Owner;

    /**
     * @ORM\OneToMany(targetEntity=Character::class, mappedBy="Room")
     * @Groups("get_room_data")
     */
    private $characters;

    /**
     * @ORM\OneToMany(targetEntity=Message::class, mappedBy="Room")
     * @Groups("get_room_data")
     */
    private $messages;

    /**
     * @ORM\Column(type="boolean")
     */
    private $closed;

    public function __construct()
    {
        $this->room_code = uniqid();
        $this->nb_player=8;
        $this->nb_agent=1;
        $this->asChrono=false;
        $this->characters = new ArrayCollection();
        $this->messages = new ArrayCollection();
        $this->closed=false;
    }



    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRoomCode(): ?string
    {
        return $this->room_code;
    }

    public function setRoomCode(string $room_code): self
    {
        $this->room_code = $room_code;

        return $this;
    }

    public function getNbPlayer(): ?int
    {
        return $this->nb_player;
    }

    public function setNbPlayer(?int $nb_player): self
    {
        $this->nb_player = $nb_player;

        return $this;
    }

    public function getNbAgent(): ?int
    {
        return $this->nb_agent;
    }

    public function setNbAgent(?int $nb_agent): self
    {
        $this->nb_agent = $nb_agent;

        return $this;
    }

    public function getAsChrono(): ?int
    {
        return $this->asChrono;
    }

    public function setAsChrono(bool $asChrono): self
    {
        $this->asChrono = $asChrono;

        return $this;
    }

   
    public function getDuration()
    {
        return $this->Duration;
    }

   
    public function setDuration($Duration)
    {
        $this->Duration = $Duration;

        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->Owner;
    }

    public function setOwner(?User $Owner): self
    {
        $this->Owner = $Owner;

        return $this;
    }

    /**
     * @return Collection<int, Character>
     */
    public function getCharacters(): Collection
    {
        return $this->characters;
    }

    public function addCharacter(Character $character): self
    {
        if (!$this->characters->contains($character)) {
            $this->characters[] = $character;
            $character->setRoom($this);
        }

        return $this;
    }

    public function removeCharacter(Character $character): self
    {
        if ($this->characters->removeElement($character)) {
            // set the owning side to null (unless already changed)
            if ($character->getRoom() === $this) {
                $character->setRoom(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Message>
     */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function addMessage(Message $message): self
    {
        if (!$this->messages->contains($message)) {
            $this->messages[] = $message;
            $message->setRoom($this);
        }

        return $this;
    }

    public function removeMessage(Message $message): self
    {
        if ($this->messages->removeElement($message)) {
            // set the owning side to null (unless already changed)
            if ($message->getRoom() === $this) {
                $message->setRoom(null);
            }
        }

        return $this;
    }

    public function isClosed(): ?bool
    {
        return $this->closed;
    }

    public function setClosed(bool $closed): self
    {
        $this->closed = $closed;

        return $this;
    }

    
}
