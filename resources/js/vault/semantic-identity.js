class SemanticIdentity {

    constructor(sourceId){
        this.sourceId = sourceId;
        this.anchor = 'Brasil';
        this.state = {
            location: [],
            segment: [],
            niche: [],
            origin: []
        };

        this.bind();
    }

    bind(){

        document
            .getElementById('semanticSearch')
            .addEventListener('input', e => this.search(e.target.value));

        document
            .getElementById('btnSaveSemanticIdentity')
            .addEventListener('click', () => this.save());
    }

    /* ============================================= */

    async search(q){

        if(q.length < 2) return;

        const r = await fetch(`/vault/source/${this.sourceId}/semantic/autocomplete?q=${encodeURIComponent(q)}`);
        const j = await r.json();

        this.renderDropdown(j.items || []);
    }

    /* ============================================= */

    add(type,item){

        if(this.state[type].some(x => x.id === item.id)) return;

        this.state[type].push(item);
        this.render();
    }

    /* ============================================= */

    render(){

        Object.keys(this.state).forEach(type => {

            const box = document.querySelector(`.semantic-group[data-type="${type}"] .pills`);
            box.innerHTML = '';

            this.state[type].forEach(item => {

                const pill = document.createElement('span');
                pill.className = 'pixip-pill';
                pill.textContent = item.label;

                pill.onclick = () => {
                    this.anchor = item.label;
                    document.getElementById('semanticAnchor').value = this.anchor;
                };

                box.appendChild(pill);
            });
        });
    }

    /* ============================================= */

    async save(){

        await fetch(`/vault/source/${this.sourceId}/semantic/save`,{
            method:'POST',
            headers:{
                'Content-Type':'application/json',
                'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content
            },
            body:JSON.stringify({
                anchor:this.anchor,
                locations:this.state.location,
                segment_ids:this.state.segment.map(x=>x.id),
                niche_ids:this.state.niche.map(x=>x.id),
                origin_ids:this.state.origin.map(x=>x.id),
            })
        });

        location.reload();
    }
}

window.openSemanticIdentity = id =>
    new SemanticIdentity(id);
