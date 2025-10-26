"use client";

import ReactMarkdown from "react-markdown";
import rehypeHighlight from "rehype-highlight";
import rehypeRaw from "rehype-raw";
import remarkGfm from "remark-gfm";
import "highlight.js/styles/github-dark.css";

interface MarkdownContentProps {
  content: string;
}

export function MarkdownContent({ content }: MarkdownContentProps) {
  return (
    <article className="markdown-content max-w-none text-gray-900 dark:text-gray-100">
      <ReactMarkdown
        remarkPlugins={[remarkGfm]}
        rehypePlugins={[rehypeHighlight, rehypeRaw]}
        components={{
          // 見出し
          h1: ({ node, ...props }) => (
            <h1
              className="text-4xl font-bold mt-8 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700"
              {...props}
            />
          ),
          h2: ({ node, ...props }) => (
            <h2
              className="text-3xl font-bold mt-8 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700"
              {...props}
            />
          ),
          h3: ({ node, ...props }) => (
            <h3 className="text-2xl font-bold mt-6 mb-3" {...props} />
          ),
          h4: ({ node, ...props }) => (
            <h4 className="text-xl font-bold mt-4 mb-2" {...props} />
          ),
          h5: ({ node, ...props }) => (
            <h5 className="text-lg font-bold mt-4 mb-2" {...props} />
          ),
          h6: ({ node, ...props }) => (
            <h6 className="text-base font-bold mt-4 mb-2" {...props} />
          ),
          // 段落
          p: ({ node, ...props }) => (
            <p className="my-4 leading-7" {...props} />
          ),
          // リンク
          a: ({ node, ...props }) => (
            <a
              {...props}
              className="text-blue-600 dark:text-blue-400 hover:underline"
              target="_blank"
              rel="noopener noreferrer"
            />
          ),
          // リスト
          ul: ({ node, ...props }) => (
            <ul className="my-4 ml-6 list-disc space-y-2" {...props} />
          ),
          ol: ({ node, ...props }) => (
            <ol className="my-4 ml-6 list-decimal space-y-2" {...props} />
          ),
          li: ({ node, ...props }) => <li className="leading-7" {...props} />,
          // コード
          code: ({ node, className, children, ...props }) => {
            const match = /language-(\w+)/.exec(className || "");
            return match ? (
              <code className={className} {...props}>
                {children}
              </code>
            ) : (
              <code
                className="bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded text-sm font-mono"
                {...props}
              >
                {children}
              </code>
            );
          },
          pre: ({ node, ...props }) => (
            <pre
              className="my-4 p-4 bg-gray-900 dark:bg-gray-950 rounded-lg overflow-x-auto"
              {...props}
            />
          ),
          // 引用
          blockquote: ({ node, ...props }) => (
            <blockquote
              className="my-4 pl-4 border-l-4 border-gray-300 dark:border-gray-600 italic text-gray-700 dark:text-gray-300"
              {...props}
            />
          ),
          // テーブル
          table: ({ node, ...props }) => (
            <div className="my-4 overflow-x-auto">
              <table
                className="min-w-full divide-y divide-gray-200 dark:divide-gray-700"
                {...props}
              />
            </div>
          ),
          thead: ({ node, ...props }) => (
            <thead className="bg-gray-50 dark:bg-gray-800" {...props} />
          ),
          tbody: ({ node, ...props }) => (
            <tbody
              className="divide-y divide-gray-200 dark:divide-gray-700"
              {...props}
            />
          ),
          tr: ({ node, ...props }) => <tr {...props} />,
          th: ({ node, ...props }) => (
            <th
              className="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider"
              {...props}
            />
          ),
          td: ({ node, ...props }) => (
            <td className="px-4 py-2 whitespace-nowrap text-sm" {...props} />
          ),
          // 水平線
          hr: ({ node, ...props }) => (
            <hr
              className="my-8 border-gray-200 dark:border-gray-700"
              {...props}
            />
          ),
          // 画像
          img: ({ node, ...props }) => (
            // biome-ignore lint/a11y/useAltText: Markdown content provides alt text
            // biome-ignore lint/performance/noImgElement: Markdown rendering needs img
            <img className="my-4 rounded-lg max-w-full h-auto" {...props} />
          ),
        }}
      >
        {content}
      </ReactMarkdown>
    </article>
  );
}
