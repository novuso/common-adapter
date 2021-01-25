<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Mail\Symfony;

use DateTime;
use DateTimeZone;
use Novuso\Common\Application\Mail\Exception\MailException;
use Novuso\Common\Application\Mail\Message\MailMessage;
use Novuso\Common\Application\Mail\Transport\MailTransport;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

/**
 * Class SymfonyMailTransport
 */
final class SymfonyMailTransport implements MailTransport
{
    protected array $overrides = [];

    /**
     * Constructs SymfonyMailTransport
     */
    public function __construct(
        protected MailerInterface $mailer,
        array $overrides = []
    ) {
        if (!empty($overrides)) {
            $this->overrides = [
                'to'  => [],
                'cc'  => [],
                'bcc' => []
            ];
            $this->setOverrides($overrides);
        }
    }

    /**
     * @inheritDoc
     */
    public function send(MailMessage $message): void
    {
        try {
            $email = new Email();
            $this->setSubject($message, $email);
            $this->setFrom($message, $email);
            $this->setReplyTo($message, $email);
            $this->setContent($message, $email);
            $this->setSender($message, $email);
            $this->setReturnPath($message, $email);
            $this->setPriority($message, $email);
            $this->setTimestamp($message, $email);
            $this->setAttachments($message, $email);

            if (!empty($this->overrides)) {
                foreach ($this->overrides['to'] as $address) {
                    $email->addTo(new Address($address));
                }
                foreach ($this->overrides['cc'] as $address) {
                    $email->addCc(new Address($address));
                }
                foreach ($this->overrides['bcc'] as $address) {
                    $email->addBcc(new Address($address));
                }
            } else {
                $this->setTo($message, $email);
                $this->setCc($message, $email);
                $this->setBcc($message, $email);
            }

            $this->mailer->send($email);
        } catch (Throwable $e) {
            throw new MailException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Sets the subject
     */
    protected function setSubject(MailMessage $message, Email $email): void
    {
        $subject = $message->getSubject();
        if ($subject !== null) {
            $email->subject($subject);
        }
    }

    /**
     * Sets the From addresses
     */
    protected function setFrom(MailMessage $message, Email $email): void
    {
        foreach ($message->getFrom() as $from) {
            $email->addFrom(new Address(
                $from['address'],
                (string) $from['name']
            ));
        }
    }

    /**
     * Sets the To addresses
     */
    protected function setTo(MailMessage $message, Email $email): void
    {
        foreach ($message->getTo() as $to) {
            $email->addTo(new Address(
                $to['address'],
                (string) $to['name']
            ));
        }
    }

    /**
     * Sets the Reply-To addresses
     */
    protected function setReplyTo(MailMessage $message, Email $email): void
    {
        foreach ($message->getReplyTo() as $replyTo) {
            $email->addReplyTo(new Address(
                $replyTo['address'],
                (string) $replyTo['name']
            ));
        }
    }

    /**
     * Sets the CC addresses
     */
    protected function setCc(MailMessage $message, Email $email): void
    {
        foreach ($message->getCc() as $cc) {
            $email->addCc(new Address(
                $cc['address'],
                (string) $cc['name']
            ));
        }
    }

    /**
     * Sets the BCC addresses
     */
    protected function setBcc(MailMessage $message, Email $email): void
    {
        foreach ($message->getBcc() as $bcc) {
            $email->addBcc(new Address(
                $bcc['address'],
                (string) $bcc['name']
            ));
        }
    }

    /**
     * Sets the content parts
     */
    protected function setContent(MailMessage $message, Email $email): void
    {
        foreach ($message->getContent() as $content) {
            if ($content['content_type'] === MailMessage::CONTENT_TYPE_HTML) {
                $email->html($content['content'], $content['charset']);
            } else {
                $email->text($content['content'], $content['charset']);
            }
        }
    }

    /**
     * Sets the sender
     */
    protected function setSender(MailMessage $message, Email $email): void
    {
        $sender = $message->getSender();
        if ($sender !== null) {
            $email->sender(new Address(
                $sender['address'],
                (string) $sender['name']
            ));
        }
    }

    /**
     * Sets the return path
     */
    protected function setReturnPath(MailMessage $message, Email $email): void
    {
        $returnPath = $message->getReturnPath();
        if ($returnPath !== null) {
            $email->returnPath(new Address($returnPath));
        }
    }

    /**
     * Sets the priority
     */
    protected function setPriority(MailMessage $message, Email $email): void
    {
        $email->priority($message->getPriority()->value());
    }

    /**
     * Sets the timestamp
     */
    protected function setTimestamp(MailMessage $message, Email $email): void
    {
        $timestamp = $message->getTimestamp();
        if ($timestamp !== null) {
            $dateTime = DateTime::createFromFormat(
                'U',
                (string) $timestamp,
                new DateTimeZone('UTC')
            );
            $email->date($dateTime);
        }
    }

    /**
     * Sets the attachments
     */
    protected function setAttachments(MailMessage $message, Email $email): void
    {
        foreach ($message->getAttachments() as $attachment) {
            if ($attachment->getDisposition() === 'inline') {
                $email->embed(
                    $attachment->getBody(),
                    $attachment->getId(),
                    $attachment->getContentType()
                );
            } else {
                $email->attach(
                    $attachment->getBody(),
                    $attachment->getFileName(),
                    $attachment->getContentType()
                );
            }
        }
    }

    /**
     * Sets override destinations
     */
    protected function setOverrides(array $overrides): void
    {
        $this->setOverride($overrides, 'to');
        $this->setOverride($overrides, 'cc');
        $this->setOverride($overrides, 'bcc');
    }

    /**
     * Sets override destinations by type
     */
    protected function setOverride(array $overrides, string $type): void
    {
        if (isset($overrides[$type])) {
            if (is_string($overrides[$type])) {
                $addresses = explode(',', $overrides[$type]);
                foreach ($addresses as $address) {
                    $this->overrides[$type][] = trim($address);
                }
            } elseif (is_array($overrides[$type])) {
                foreach ($overrides[$type] as $address) {
                    $this->overrides[$type][] = trim($address);
                }
            }
        }
    }
}
