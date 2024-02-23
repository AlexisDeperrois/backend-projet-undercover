<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\CharacterRepository;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass=CharacterRepository::class)
 * @ORM\Table(name="`character`")
 */
class Character
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     * @Groups("get_room_data")
     * 
     * 
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=30, nullable=true)
     */
    private $role;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * 
     */
    private $word;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $vote_count;

    /**
     * @ORM\Column(type="boolean")
     * @Groups("get_room_data")
     */
    private $is_eliminated;

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="characters")
     * @ORM\JoinColumn(nullable=false)
     * @Groups("get_room_data")
     */
    private $User;

    /**
     * @ORM\ManyToOne(targetEntity=Room::class, inversedBy="characters")
     * @ORM\JoinColumn(nullable=false)
     */
    private $Room;

    /**
     * @ORM\Column(type="boolean")
     * @Groups("get_room_data")
     */
    private $voted;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Groups("get_room_data")
     */
    private $hint;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $roundOrder;

    public function __construct(){
        $this->vote_count=0;
        $this->is_eliminated=false;
        $this->voted=false;
        
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(?string $role): self
    {
        $this->role = $role;

        return $this;
    }

    public function getWord(): ?string
    {
        return $this->word;
    }

    public function setWord(?string $word): self
    {
        $this->word = $word;

        return $this;
    }

    public function getVoteCount(): ?int
    {
        return $this->vote_count;
    }

    public function setVoteCount(?int $vote_count): self
    {
        $this->vote_count = $vote_count;

        return $this;
    }

    public function isIsEliminated(): ?bool
    {
        return $this->is_eliminated;
    }

    public function setIsEliminated(bool $is_eliminated): self
    {
        $this->is_eliminated = $is_eliminated;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->User;
    }

    public function setUser(?User $User): self
    {
        $this->User = $User;

        return $this;
    }

    public function getRoom(): ?Room
    {
        return $this->Room;
    }

    public function setRoom(?Room $Room): self
    {
        $this->Room = $Room;

        return $this;
    }

    public function isVoted(): ?bool
    {
        return $this->voted;
    }

    public function setVoted(bool $voted): self
    {
        $this->voted = $voted;

        return $this;
    }

    public function getHint(): ?string
    {
        return $this->hint;
    }

    public function setHint(?string $hint): self
    {
        $this->hint = $hint;

        return $this;
    }

    public function getRoundOrder(): ?int
    {
        return $this->roundOrder;
    }

    public function setRoundOrder(?int $roundOrder): self
    {
        $this->roundOrder = $roundOrder;

        return $this;
    }
}
