export default function initSemantic(){

    const list   = document.getElementById('sourcesList')
    const editor = document.getElementById('semanticEditor')
    const empty  = document.getElementById('semanticEmpty')

    if(!list) return

    loadSources()

    async function loadSources(){

        const r = await fetch('/vault/semantic/sources')
        const j = await r.json()

        list.innerHTML = j.map(s => `
            <li class="list-group-item"
                data-id="${s.id}">
                ${s.original_name}
            </li>
        `).join('')

        list.querySelectorAll('li').forEach(li=>{
            li.onclick = () => openEditor(li.dataset.id)
        })
    }

    async function openEditor(id){

        empty.classList.add('d-none')
        editor.classList.remove('d-none')

        const r = await fetch(`/vault/semantic/${id}`)
        const d = await r.json()

        document.getElementById('editorTitle').textContent = d.source.original_name

        fillSelect('segmentSelect', d.segments, d.semantic.segment_id)
        fillSelect('nicheSelect',   d.niches,   d.semantic.niche_id)
        fillSelect('originSelect',  d.origins,  d.semantic.origin_id)

        renderLocations(d.locations)
    }

    function fillSelect(id, list, selected){

        const el = document.getElementById(id)

        el.innerHTML = list.map(x =>
            `<option value="${x.id}" ${x.id==selected?'selected':''}>${x.name}</option>`
        ).join('')
    }

    function renderLocations(locations){

        const box = document.getElementById('locationChips')

        box.innerHTML = locations.map(l =>
            `<span class="semantic-chip">
                ${l.name}
                <button>×</button>
            </span>`
        ).join('')
    }
}
