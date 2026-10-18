// Wake's editor: Tiptap that reads and writes Markdown, with Wake's cards
// (::: name … :::, with forms from their descriptions), images from the
// media library and a slash menu.
import { Editor, Node, mergeAttributes } from '@tiptap/core'
import StarterKit from '@tiptap/starter-kit'
import Image from '@tiptap/extension-image'
import Placeholder from '@tiptap/extension-placeholder'
import { Markdown } from '@tiptap/markdown'

const MEDIA_URL = /\/media\/[a-f0-9]{10}\/[a-z0-9]+\/[^/]+\/([^/?#]+)$/

// Splits the text after a card's name into key="value" pairs and the rest,
// like Wake\Markdown\Cards\Card::parseArguments().
function parseArgs(args) {
  const attrs = {}
  const rest = (args || '').replace(/(^|\s)([a-z][a-z0-9_-]*)=(?:"([^"]*)"|'([^']*)'|(\S+))/gi, (_, space, key, a, b, c) => {
    attrs[key.toLowerCase()] = a !== undefined ? a : b !== undefined ? b : c
    return space
  })
  return { attrs, value: rest.replace(/\s+/g, ' ').trim() }
}

function quote(text) {
  text = String(text).replace(/[\r\n]+/g, ' ')
  return text.includes('"') ? "'" + text.replace(/'/g, '’') + "'" : '"' + text + '"'
}

// Writes value and settings back: the value first, then the described
// fields in their order, then settings the description does not know.
function buildArgs(type, value, attrs) {
  const keys = [...type.fields.map(f => f.key), ...Object.keys(attrs).filter(k => !type.fields.some(f => f.key === k))]
  const parts = type.value && value ? [value] : []
  keys.forEach(key => { if (attrs[key] !== undefined && attrs[key] !== '') parts.push(key + '=' + quote(attrs[key])) })
  return parts.join(' ')
}

// One input per setting of a described card.
function fieldInput(field, current, files, onChange) {
  const label = document.createElement('label')
  label.className = 'wake-card__field' + (field.required ? ' is-required' : '')
  const caption = document.createElement('span')
  caption.textContent = field.label
  let input
  if (field.type === 'select') {
    input = document.createElement('select')
    input.append(new Option('', ''), ...Object.entries(field.options).map(([v, l]) => new Option(l, v)))
    input.value = current
  } else if (field.type === 'toggle') {
    input = document.createElement('input')
    input.type = 'checkbox'
    input.checked = current === 'true'
  } else {
    input = document.createElement('input')
    input.type = { url: 'text', number: 'number', date: 'date', color: 'text' }[field.type] || 'text'
    input.value = current
    input.placeholder = field.placeholder || (field.type === 'image' ? 'photo.jpg' : '')
    if (field.type === 'image' && files.length) {
      const list = document.createElement('datalist')
      list.id = 'wake-files-' + Math.random().toString(36).slice(2)
      files.forEach(f => list.append(new Option(f)))
      input.setAttribute('list', list.id)
      label.append(list)
    }
  }
  const read = () => onChange(field.type === 'toggle' ? (input.checked ? 'true' : '') : input.value.trim())
  input.addEventListener('change', read)
  label.append(caption, input)
  return label
}

// A card keeps its name and the rest of the opening line as written, and
// holds normal Markdown content. Cards with a description (cards/{name}.yml)
// get a small form for their settings instead of the raw line.
function wakeCard(types, files) {
  const byName = Object.fromEntries(types.map(t => [t.name, t]))

  return Node.create({
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
        const type = byName[node.attrs.name]
        const save = args => {
          if (typeof getPos === 'function') editor.chain().command(({ tr }) => { tr.setNodeMarkup(getPos(), undefined, { ...node.attrs, args }); return true }).run()
        }
        const dom = document.createElement('div')
        dom.className = 'wake-card wake-card--' + node.attrs.name
        const head = document.createElement('div')
        head.className = 'wake-card__head'
        head.contentEditable = 'false'
        const label = document.createElement('span')
        label.className = 'wake-card__name'
        label.textContent = type ? type.title : node.attrs.name
        label.title = type && type.description ? type.description : node.attrs.name
        head.append(label)
        const parts = [head]

        if (type) {
          const { attrs, value } = parseArgs(node.attrs.args)
          const state = { attrs: { ...attrs }, value }
          const form = document.createElement('div')
          form.className = 'wake-card__form'
          form.contentEditable = 'false'
          const update = () => save(buildArgs(type, state.value, state.attrs))
          if (type.value) form.append(fieldInput({ ...type.value, type: 'text', key: '' }, value, files, v => { state.value = v; update() }))
          type.fields.forEach(field => form.append(fieldInput(field, attrs[field.key] ?? '', files, v => { state.attrs[field.key] = v; update() })))
          if (form.childNodes.length) parts.push(form)
        } else {
          const args = document.createElement('input')
          args.className = 'wake-card__args'
          args.value = node.attrs.args
          args.addEventListener('change', () => save(args.value))
          head.append(args)
        }

        const content = document.createElement('div')
        content.className = 'wake-card__body'
        content.hidden = !!type && !type.content
        dom.append(...parts, content)
        // The head and form are not part of the document: typing, clicking
        // and selecting there must not reach the editor.
        const outside = target => !content.contains(target)
        return { dom, contentDOM: content, stopEvent: e => outside(e.target), ignoreMutation: m => m.type === 'selection' ? false : outside(m.target) }
      }
    },

    markdownTokenizer: {
      name: 'card',
      level: 'block',
      start: src => src.search(/^:::[ \t]*[a-z]/m),
      tokenize(src, _tokens, lexer) {
        // "::: name …" with content up to a line ":::" (the content may be
        // empty), or "::: name … :::" on one line.
        const one = /^:::[ \t]*([a-z][a-z0-9-]*)([^\n]*?)[ \t]*:::[ \t]*(?:\n|$)/.exec(src)
        if (one) return { type: 'card', raw: one[0], name: one[1], args: one[2].trim(), tokens: [] }
        const match = /^:::[ \t]*([a-z][a-z0-9-]*)([^\n]*)\n(?:([\s\S]*?)\n)?:::[ \t]*(?:\n|$)/.exec(src)
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
}

// The / menu entry for a described card, with its defaults filled in.
function cardItem(type) {
  const attrs = Object.fromEntries(type.fields.filter(f => f.default).map(f => [f.key, f.default]))
  const value = type.value && type.value.placeholder && !type.value.required ? type.value.placeholder : ''
  return {
    key: 'card:' + type.name,
    label: type.title,
    hint: type.hint,
    run: c => c.insertContent({ type: 'card', attrs: { name: type.name, args: buildArgs(type, value, attrs) }, content: type.content ? [{ type: 'paragraph' }] : [] }),
  }
}

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

export function create(element, { markdown, media = {}, cards = [], labels = {}, placeholder = '', onChange, onUpload }) {
  const slashItems = [...SLASH_ITEMS, ...cards.filter(t => t.menu).map(cardItem)]
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
      wakeCard(cards, Object.keys(media)),
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
    items = slashItems.filter(i => (labels[i.key] || i.label).toLowerCase().includes(query) || i.key.includes(query))
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
