# Feilsøkingsplan: Display oppdaterer ikke aktive spørsmål

## Hypoteser (rangert etter sannsynlighet)

### 1. JavaScript initialiseringstiming ⭐⭐⭐⭐⭐
**Problem:** SSE starter før DOM er klar, eller `lastStates` initialiseres feil.
**Test:** Sjekk browser console for "SSE connected" melding.

### 2. State mismatch mellom PHP og SSE ⭐⭐⭐⭐
**Problem:** PHP sier `activeQuestion = null`, men SSE sender `activeQuestionId = X`.
**Test:** Sammenlign PHP output med SSE output.

### 3. Caching på server ⭐⭐⭐
**Problem:** display.php caches med gammel data.
**Test:** Sjekk headers, legg til cache-busting.

### 4. JavaScript errors ⭐⭐⭐
**Problem:** Script kræsjer før event listeners er satt.
**Test:** Sjekk browser console for errors.

### 5. Database replication lag ⭐⭐
**Problem:** SSE leser gammel data fra DB.
**Test:** Legg til timestamp logging.

---

## Steg-for-steg plan (én ting av gangen)

### STEG 1: Verifiser SSE fungerer (5 min)
**Hva:** Sjekk at SSE connection etableres ved sidelast.
**Hvordan:**
1. Åpne display.php i ny tab
2. Åpne DevTools → Network → filter "sse"
3. Se etter grønn status og "connected" event
4. Sjekk Console for "SSE connected"

**Forventet:** Grønn dot, "SSE connected" i console.
**Hvis feil:** SSE initialiseres ikke riktig → fiks connectSSE().

---

### STEG 2: Verifiser state ved sidelast (5 min)
**Hva:** Sjekk at `lastStates` initialiseres riktig fra PHP.
**Hvordan:**
1. I display.php, legg til i JS:
   ```javascript
   console.log('Initial state:', lastStates);
   ```
2. Refresh display.php
3. Sjekk console

**Forventet:** `activeQuestionId` skal være `null` (hvis ingen aktiv) eller et tall.
**Hvis feil:** PHP sender feil verdier → sjekk PHP i display.php.

---

### STEG 3: Test SSE oppdatering (5 min)
**Hva:** Sjekk at SSE mottar data når du aktiverer spørsmål.
**Hvordan:**
1. Ha display.php åpen med DevTools → Network → SSE
2. I dashboard: aktiver et spørsmål
3. Se i Network tab: kommer det ny "update" event?

**Forventet:** Ny rad i Network tab med "update" event etter ~100ms.
**Hvis feil:** SSE sender ikke data → sjekk api/sse.php.

---

### STEG 4: Verifiser handleStateUpdate (5 min)
**Hva:** Sjekk at JavaScript håndterer SSE data.
**Hvordan:**
1. Legg til logging:
   ```javascript
   function handleStateUpdate(data) {
       console.log('State update received:', data);
       // ... rest
   }
   ```
2. Aktiver spørsmål i dashboard
3. Se i console

**Forventet:** "State update received" med `activeQuestionId` satt.
**Hvis feil:** handleStateUpdate kalles ikke → sjekk event listener.

---

### STEG 5: Verifiser transitionQuestion (5 min)
**Hva:** Sjekk at spørsmålspanelet faktisk oppdateres.
**Hvordan:**
1. Legg til logging:
   ```javascript
   function transitionQuestion(oldId, newId, questionData) {
       console.log('Transition:', oldId, '->', newId, questionData);
       // ... rest
   }
   ```
2. Aktiver spørsmål
3. Se i console

**Forventet:** "Transition: null -> [id]" med questionData.
**Hvis feil:** state comparison feiler → sjekk type casting.

---

## Rask fiks å teste først

Legg til dette i display.php (lokalt, ikke commit enda):

```javascript
// Force reconnection on visibility change
document.addEventListener('visibilitychange', () => {
    if (!document.hidden && eventSource) {
        console.log('Reconnecting SSE...');
        eventSource.close();
        setTimeout(connectSSE, 100);
    }
});
```

Dette reloader SSE når tab blir aktiv - kan hjelpe hvis problemet er stale connections.

---

## Neste steg

Skal vi starte med **STEG 1** (verifisere SSE connection)?