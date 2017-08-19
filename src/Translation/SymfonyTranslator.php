<?php declare(strict_types=1);

namespace Novuso\Common\Adapter\Translation;

use Novuso\Common\Application\Translation\Exception\TranslationException;
use Novuso\Common\Application\Translation\TranslatorInterface;
use Symfony\Component\Translation\TranslatorInterface as Translator;
use Throwable;

/**
 * SymfonyTranslator is a Symfony translation adapter
 *
 * @copyright Copyright (c) 2017, Novuso. <http://novuso.com>
 * @license   http://opensource.org/licenses/MIT The MIT License
 * @author    John Nickell <email@johnnickell.com>
 */
class SymfonyTranslator implements TranslatorInterface
{
    /**
     * Translator
     *
     * @var Translator
     */
    protected $translator;

    /**
     * Constructs SymfonyTranslator
     *
     * @param Translator $translator The Symfony translator
     */
    public function __construct(Translator $translator)
    {
        $this->translator = $translator;
    }

    /**
     * {@inheritdoc}
     */
    public function translate(
        string $key,
        array $parameters = [],
        ?string $domain = null,
        ?string $locale = null
    ): string {
        try {
            return $this->translator->trans($key, $parameters, $domain, $locale);
        } catch (Throwable $e) {
            throw new TranslationException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function choice(
        string $key,
        int $index,
        array $parameters = [],
        ?string $domain = null,
        ?string $locale = null
    ): string {
        try {
            return $this->translator->transChoice($key, $index, $parameters, $domain, $locale);
        } catch (Throwable $e) {
            throw new TranslationException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
