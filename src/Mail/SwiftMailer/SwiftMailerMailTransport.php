<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Mail\SwiftMailer;

use DateTime;
use DateTimeZone;
use Novuso\Common\Application\Mail\Exception\MailException;
use Novuso\Common\Application\Mail\Message\MailMessage;
use Novuso\Common\Application\Mail\Transport\MailTransport;
use Swift_Attachment;
use Swift_Mailer;
use Swift_Message;
use Throwable;

/**
 * Class SwiftMailerMailTransport
 */
final class SwiftMailerMailTransport implements MailTransport
{
    protected array $overrides = [];

    /**
     * Constructs SwiftMailerEmailTransport
     */
    public function __construct(
        protected Swift_Mailer $mailer,
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
            $swiftMessage = new Swift_Message();
            $this->setCharset($message, $swiftMessage);
            $this->setSubject($message, $swiftMessage);
            $this->setFrom($message, $swiftMessage);
            $this->setReplyTo($message, $swiftMessage);
            $this->setContent($message, $swiftMessage);
            $this->setSender($message, $swiftMessage);
            $this->setReturnPath($message, $swiftMessage);
            $this->setPriority($message, $swiftMessage);
            $this->setTimestamp($message, $swiftMessage);
            $this->setMaxLineLength($message, $swiftMessage);
            $this->setAttachments($message, $swiftMessage);

            if (!empty($this->overrides)) {
                foreach ($this->overrides['to'] as $address) {
                    $swiftMessage->addTo($address);
                }
                foreach ($this->overrides['cc'] as $address) {
                    $swiftMessage->addCc($address);
                }
                foreach ($this->overrides['bcc'] as $address) {
                    $swiftMessage->addBcc($address);
                }
            } else {
                $this->setTo($message, $swiftMessage);
                $this->setCc($message, $swiftMessage);
                $this->setBcc($message, $swiftMessage);
            }

            $this->mailer->send($swiftMessage);
        } catch (Throwable $e) {
            throw new MailException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Sets the charset
     */
    protected function setCharset(
        MailMessage $message,
        Swift_Message $swiftMessage
    ): void {
        $swiftMessage->setCharset($message->getCharset());
    }

    /**
     * Sets the subject
     */
    protected function setSubject(
        MailMessage $message,
        Swift_Message $swiftMessage
    ): void {
        $subject = $message->getSubject();
        if ($subject !== null) {
            $swiftMessage->setSubject($subject);
        }
    }

    /**
     * Sets the From addresses
     */
    protected function setFrom(
        MailMessage $message,
        Swift_Message $swiftMessage
    ): void {
        foreach ($message->getFrom() as $from) {
            $swiftMessage->addFrom($from['address'], $from['name']);
        }
    }

    /**
     * Sets the To addresses
     */
    protected function setTo(
        MailMessage $message,
        Swift_Message $swiftMessage
    ): void {
        foreach ($message->getTo() as $to) {
            $swiftMessage->addTo($to['address'], $to['name']);
        }
    }

    /**
     * Sets the Reply-To addresses
     */
    protected function setReplyTo(
        MailMessage $message,
        Swift_Message $swiftMessage
    ): void {
        foreach ($message->getReplyTo() as $replyTo) {
            $swiftMessage->addReplyTo($replyTo['address'], $replyTo['name']);
        }
    }

    /**
     * Sets the CC addresses
     */
    protected function setCc(
        MailMessage $message,
        Swift_Message $swiftMessage
    ): void {
        foreach ($message->getCc() as $cc) {
            $swiftMessage->addCc($cc['address'], $cc['name']);
        }
    }

    /**
     * Sets the BCC addresses
     */
    protected function setBcc(
        MailMessage $message,
        Swift_Message $swiftMessage
    ): void {
        foreach ($message->getBcc() as $bcc) {
            $swiftMessage->addBcc($bcc['address'], $bcc['name']);
        }
    }

    /**
     * Sets the content parts
     */
    protected function setContent(
        MailMessage $message,
        Swift_Message $swiftMessage
    ): void {
        $bodySet = false;
        foreach ($message->getContent() as $content) {
            if (!$bodySet) {
                $swiftMessage->setBody(
                    $content['content'],
                    $content['content_type'],
                    $content['charset']
                );
                $bodySet = true;
            } else {
                $swiftMessage->addPart(
                    $content['content'],
                    $content['content_type'],
                    $content['charset']
                );
            }
        }
    }

    /**
     * Sets the sender
     */
    protected function setSender(
        MailMessage $message,
        Swift_Message $swiftMessage
    ): void {
        $sender = $message->getSender();
        if ($sender !== null) {
            $swiftMessage->setSender($sender['address'], $sender['name']);
        }
    }

    /**
     * Sets the return path
     */
    protected function setReturnPath(
        MailMessage $message,
        Swift_Message $swiftMessage
    ): void {
        $returnPath = $message->getReturnPath();
        if ($returnPath !== null) {
            $swiftMessage->setReturnPath($returnPath);
        }
    }

    /**
     * Sets the priority
     */
    protected function setPriority(
        MailMessage $message,
        Swift_Message $swiftMessage
    ): void {
        $swiftMessage->setPriority($message->getPriority()->value());
    }

    /**
     * Sets the timestamp
     */
    protected function setTimestamp(
        MailMessage $message,
        Swift_Message $swiftMessage
    ): void {
        $timestamp = $message->getTimestamp();
        if ($timestamp !== null) {
            $dateTime = DateTime::createFromFormat(
                'U',
                (string) $timestamp,
                new DateTimeZone('UTC')
            );
            $swiftMessage->setDate($dateTime);
        }
    }

    /**
     * Sets the max line length
     */
    protected function setMaxLineLength(
        MailMessage $message,
        Swift_Message $swiftMessage
    ): void {
        $maxLineLength = $message->getMaxLineLength();
        if ($maxLineLength !== null) {
            $swiftMessage->setMaxLineLength($maxLineLength);
        }
    }

    /**
     * Sets the attachments
     */
    protected function setAttachments(
        MailMessage $message,
        Swift_Message $swiftMessage
    ): void {
        foreach ($message->getAttachments() as $attachment) {
            $swiftAttachment = new Swift_Attachment(
                $attachment->getBody(),
                $attachment->getFileName(),
                $attachment->getContentType()
            );
            $swiftAttachment->setId($attachment->getId());
            $swiftAttachment->setDisposition($attachment->getDisposition());
            $swiftMessage->attach($swiftAttachment);
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
