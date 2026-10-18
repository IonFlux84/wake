// Wake's editor: Tiptap that reads and writes Markdown, with Wake's cards
// (::: name … :::), images from the entry's folder and a slash menu.
import { Editor, Node, mergeAttributes } from '@tiptap/core'
import StarterKit from '@tiptap/starter-kit'
import Image from '@tiptap/extension-image'
import Placeholder from '@tiptap/extension-placeholder'
import { Markdown } from '@tiptap/markdown'

const MEDIA_URL = /\/media\/[a-f0-9]{10}\/[a-z0-9]+\/[^/]+\/([^/?#]+)$/

// A card keeps its name and the rest of the opening line as written, and
// holds normal Markdown content.
const Card = Node.create({
  name: 'card',
  group: 'block',
  content: 'block*',
  defining: true,

  addAttributes() {
    return { name: { default: 'callout' }, args: { default: '' } }
  },

  parseHTML() {
    return [{ tag: 'div[data-card]', getAttrs: el => ({ name: el.dataset.card, args: el.dataset.args || '' }) }]
  },

  renderHTML({ node, HTMLAttributes }) {
    return ['div', mergeAttributes(HTMLAttributes, { 'data-card': node.attrs.name, 'data-args': node.attrs.args, class: 'wake-card' }), 0]
  },

  addNodeView() {
    return ({ node, getPos, editor }) => {
      const dom = document.createElement('div')
      dom.className = 'wake-card wake-card--' + node.attrs.name
      const head = document.createElement('div')
      head.className = 'wake-card__head'
      head.contentEditable = 'false'
      const label = document.createElement('span')
      label.className = 'wake-card__name'
      label.textContent = node.attrs.name
      const args = document.createElement('input')
      args.className = 'wake-card__args'
      args.value = node.attrs.args
      args.placeholder = { callout: '💡', button: 'href="/…"', embed: 'https://…' }[node.attrs.name] || ''
      args.addEventListener('change', () => {
        if (typeof getPos === 'function') editor.chain().command(({ tr }) => { tr.setNodeMarkup(getPos(), undefined, { ...node.attrs, args: args.value }); return true }).run()
      })
      head.append(label, args)
      const content = document.createElement('div')
      content.className = 'wake-card__body'
      dom.append(head, content)
      return { dom, contentDOM: content, ignoreMutation: m => head.contains(m.target) }
    }
  },

  markdownTokenizer: {
    name: 'card',
    level: 'block',
    start: src => src.search(/^:::[ \t]*[a-z]/m),
    tokenize(src, _tokens, lexer) {
      const match = /^:::[ \t]*([a-z][a-z0-9-]*)([^\n]*)\n([\s\S]*?)(?:^|\n):::[ \t]*(?:\n|$)/.exec(src)
      if (!match) return undefined
      return { type: 'card', raw: match[0], name: match[1], args: match[2].trim(), tokens: lexer.blockTokens(match[3] || '') }
    },
  },

  parseMarkdown(token, helpers) {
    return helpers.createNode('card', { name: token.name, args: token.args }, helpers.parseChildren(token.tokens || []))
  },

  renderMarkdown(node, helpers) {
    const inner = helpers.renderChildren(node.content || [], '\n\n').trim()
    return ':::' + ' ' + node.attrs.name + (node.attrs.args ? ' ' + node.attrs.args : '') + '\n' + (inner ? inner + '\n' : '') + ':::'
  },
})

// Images are stored by file name; the editor shows them through their media URL.
function wakeImage(media) {
  return Image.extend({
    parseMarkdown(token, helpers) {
      const src = media[token.href] || token.href
      return helpers.createNode('image', { src, alt: token.text || '', title: token.title || null })
    },
    renderMarkdown(node) {
      const match = MEDIA_URL.exec(node.attrs.src || '')
      const src = match ? decodeURIComponent(match[1]) : node.attrs.src
      return '![' + (node.attrs.alt || '') + '](' + src + (node.attrs.title ? ' "' + node.attrs.title + '"' : '') + ')'
    },
  })
}

const SLASH_ITEMS = [
  { key: 'h2', label: 'Heading', hint: 'H2', run: c => c.setNode('heading', { level: 2 }) },
  { key: 'h3', label: 'Subheading', hint: 'H3', run: c => c.setNode('heading', { level: 3 }) },
  { key: 'ul', label: 'List', hint: '•', run: c => c.toggleBulletList() },
  { key: 'ol', label: 'Numbered list', hint: '1.', run: c => c.toggleOrderedList() },
  { key: 'quote', label: 'Quote', hint: '“', run: c => c.toggleBlockquote() },
  { key: 'code', label: 'Code', hint: '</>', run: c => c.toggleCodeBlock() },
  { key: 'divider', label: 'Divider', hint: '—', run: c => c.setHorizontalRule() },
  { key: 'image', label: 'Image', hint: '▣', run: null },
  { key: 'callout', label: 'Callout', hint: '💡', run: c => c.insertContent({ type: 'card', attrs: { name: 'callout', args: '💡' }, content: [{ type: 'paragraph' }] }) },
  { key: 'button', label: 'Button', hint: '⬭', run: c => c.insertContent({ type: 'card', attrs: { name: 'button', args: 'href="/"' }, content: [{ type: 'paragraph', content: [{ type: 'text', text: 'Button' }] }] }) },
  { key: 'embed', label: 'Embed (YouTube, map …)', hint: '▶', run: c => c.insertContent({ type: 'card', attrs: { name: 'embed', args: 'https://' }, content: [] }) },
]

// Soft line breaks inside paragraphs mean nothing in Markdown, but the
// browser turns them into hard breaks once a paragraph is edited. Joining
// them with a space keeps the meaning and writes one line per paragraph.
function joinSoftBreaks(json) {
  const walk = node => {
    if (node.type === 'codeBlock') return node
    if (node.type === 'text' && node.text && node.text.includes('\n')) return { ...node, text: node.text.replace(/[ \t]*\n[ \t]*/g, ' ') }
    return node.content ? { ...node, content: node.content.map(walk) } : node
  }
  return walk(json)
}

export function create(element, { markdown, media = {}, labels = {}, placeholder = '', onChange, onUpload }) {
  const menu = document.createElement('div')
  menu.className = 'wake-slash'
  menu.hidden = true
  document.body.appendChild(menu)
  let active = 0
  let items = []

  const editor = new Editor({
    element,
    extensions: [
      StarterKit.configure({ link: { openOnClick: false } }),
      wakeImage(media),
      Card,
      Placeholder.configure({ placeholder }),
      Markdown.configure({ markedOptions: { gfm: true, breaks: false } }),
    ],
    content: markdown,
    contentType: 'markdown',
    onUpdate: ({ editor }) => { onChange && onChange(editor.getMarkdown()); slash() },
    onSelectionUpdate: () => slash(),
    onCreate: ({ editor }) => editor.commands.setContent(joinSoftBreaks(editor.getJSON()), { emitUpdate: false }),
  })

  function pickImage() {
    const input = document.createElement('input')
    input.type = 'file'
    input.accept = 'image/*'
    input.onchange = () => input.files[0] && upload(input.files[0])
    input.click()
  }

  function upload(file, alt = '') {
    if (!onUpload || !file) return
    onUpload(file).then(result => result && editor.chain().focus().setImage({ src: result.url, alt }).run())
  }

  function slash() {
    const { $from, empty } = editor.state.selection
    const text = $from.parent.textContent
    if (!empty || $from.parent.type.name !== 'paragraph' || !text.startsWith('/') || text.length > 20) { menu.hidden = true; return }
    const query = text.slice(1).toLowerCase()
    items = SLASH_ITEMS.filter(i => (labels[i.key] || i.label).toLowerCase().includes(query) || i.key.includes(query))
    if (!items.length) { menu.hidden = true; return }
    active = Math.min(active, items.length - 1)
    menu.innerHTML = ''
    items.forEach((item, i) => {
      const b = document.createElement('button')
      b.type = 'button'
      b.className = 'wake-slash__item' + (i === active ? ' is-active' : '')
      b.innerHTML = '<span class="wake-slash__hint"></span><span></span>'
      b.firstChild.textContent = item.hint
      b.lastChild.textContent = labels[item.key] || item.label
      b.onmousedown = e => { e.preventDefault(); choose(item) }
      menu.appendChild(b)
    })
    const pos = editor.view.coordsAtPos($from.pos)
    menu.style.left = (pos.left + window.scrollX) + 'px'
    menu.style.top = (pos.bottom + window.scrollY + 8) + 'px'
    menu.hidden = false
  }

  function choose(item) {
    const { $from } = editor.state.selection
    editor.chain().focus().deleteRange({ from: $from.start(), to: $from.end() }).run()
    menu.hidden = true
    if (item.key === 'image') return pickImage()
    item.run(editor.chain().focus()).run()
  }

  editor.view.dom.addEventListener('keydown', e => {
    if (menu.hidden) return
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); active = (active + (e.key === 'ArrowDown' ? 1 : items.length - 1)) % items.length; slash() }
    else if (e.key === 'Enter') { e.preventDefault(); choose(items[active]) }
    else if (e.key === 'Escape') { menu.hidden = true }
  }, true)

  element.addEventListener('drop', e => {
    const file = e.dataTransfer && e.dataTransfer.files[0]
    if (file && file.type.startsWith('image/')) { e.preventDefault(); upload(file) }
  })
  element.addEventListener('paste', e => {
    const file = e.clipboardData && [...e.clipboardData.files].find(f => f.type.startsWith('image/'))
    if (file) { e.preventDefault(); upload(file) }
  })

  return {
    editor,
    load: markdown => {
      editor.commands.setContent(markdown, { contentType: 'markdown', emitUpdate: false })
      editor.commands.setContent(joinSoftBreaks(editor.getJSON()), { emitUpdate: false })
    },
    getMarkdown: () => editor.getMarkdown(),
    upload,
    command: name => {
      const c = editor.chain().focus()
      ;({ bold: () => c.toggleBold(), italic: () => c.toggleItalic(), link: () => {
        const prev = editor.getAttributes('link').href || ''
        const href = window.prompt(labels.link || 'Link', prev)
        return href === null ? c : href === '' ? c.unsetLink() : c.setLink({ href })
      }, h2: () => c.toggleHeading({ level: 2 }), quote: () => c.toggleBlockquote(), ul: () => c.toggleBulletList() })[name]().run()
    },
    destroy: () => { editor.destroy(); menu.remove() },
  }
}
