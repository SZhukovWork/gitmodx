<?php

class GitmodxService
{
    public ModX $modx;
    private string $elementsPath;
    public const modElementsExtension = [
        "php" => [
            modSnippet::class,
            modPlugin::class,
        ],
        "tpl" => [
            modTemplate::class,
            modChunk::class,
        ],
    ];
    public const modElementsFolder = [
        modChunk::class => "chunks",
        modTemplate::class => "templates",
        modSnippet::class => "snippets",
        modPlugin::class => "plugins",
    ];
    public const source = 1;

    public function __construct(ModX $modx, string $elementsPath = null)
    {
        $this->modx = $modx;
        if (!$elementsPath) {
            $elementsPath = MODX_CORE_PATH . "/components/gitmodx/elements/";
        }
        $this->elementsPath = $elementsPath;
    }

    public function importChunks(?string $folder = null)
    {
        $this->importElements(modChunk::class, $folder?: $this->getModElementFolder(modChunk::class));
    }

    public function importPlugin(?string $folder = null)
    {
        $this->importElements(modPlugin::class, $folder?: $this->getModElementFolder(modPlugin::class));
    }

    public function importTemplates(?string $folder = null)
    {
        $this->importElements(modTemplate::class, $folder?: $this->getModElementFolder(modTemplate::class));
    }

    public function importSnippets(?string $folder = null)
    {
        $this->importElements(modSnippet::class,$folder?: $this->getModElementFolder(modSnippet::class));
    }

    public function loadElements(string $class)
    {
        //TODO: подгрузка в бд для админки
    }

    public function importElements(string $class, string $folder)
    {
        $elements = $this->modx->getCollection($class);
        $elementsPath = $this->elementsPath . $folder . "/";
        foreach ($elements as $element) {
            /** @var modScript|modTemplate $element */
            $path = $elementsPath . $this->getModElementFilePath($element);
            $path = $this->normalizePath($path);
            if (!$this->createDir(dirname($path))) {
                $this->modx->log(MODX_LOG_LEVEL_ERROR, "Не удалось создать папку: " . dirname($path));
                continue;
            }
            if (!file_put_contents($path, $element->getContent(), LOCK_EX)) {
                $this->modx->log(MODX_LOG_LEVEL_ERROR, "Не удалось создать файл: " . $path);
                continue;
            }
            $staticPath = str_replace($this->normalizePath(MODX_BASE_PATH), "", $path);
            $element->set("static", true);
            $element->set("static_file", $staticPath);
            $element->set('source', self::source);
            if (!$element->save()) {
                $this->modx->log(MODX_LOG_LEVEL_ERROR, "Не удалось сохранить модель: " . $class);
            }
        }
    }

    private function getModElementFilePath(modElement $modElement): string
    {
        if ($modElement instanceof modTemplate) {
            $path = $modElement->get("templatename");
        } else if ($modElement instanceof modScript) {
            $path = $modElement->get("name");
        }
        if (empty($path)) {
            throw new Exception("Не могу получить название элемента " . get_class($modElement));
        }
        $path .= "." . $this->getModElementExtension($modElement);
        $category = $modElement->getOne("Category");
        if ($category) {
            $path = $this->getCategoryPath($category) . $path;
        }
        return $path;
    }

    /**
     * @param $modElement
     * @return string|modElement
     */
    public function getModElementExtension($modElement): ?string
    {
        foreach (self::modElementsExtension as $key => $classes) {
            foreach ($classes as $class) {
                if ($modElement instanceof $class) {
                    return $key;
                }
            }
        }
        return null;
    }

    /**
     * @param $modElement
     * @return string|modElement
     */
    public function getModElementFolder($modElement): ?string
    {
        foreach (self::modElementsFolder as $class => $path) {
            if ($modElement instanceof $class) {
                return $path;
            }
        }
        return null;
    }

    private function getCategoryPath(modCategory $category)
    {
        $path = $category->get("category") . "/";
        /** @var ?modCategory $parent */
        $parent = $category->getOne("Parent");
        if ($parent) {
            $path .= $this->getCategoryPath($parent);
        }
        return $path;
    }

    private function createDir(string $path)
    {
        if (!is_dir($path)) {
            return mkdir($path, 0777, true);
        }
        return true;
    }

    private function normalizePath(string $path)
    {
        return preg_replace('/[\\\/]+/g', "/", $path);
    }
}