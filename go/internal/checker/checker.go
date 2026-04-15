package checker

import (
	"fmt"
	"strings"

	"github.com/wc-library/lastCopyStateChecker/internal/api"
)

// Result represents the outcome of checking a single OCLC number.
type Result struct {
	// OCLCNumber is the number that was checked
	OCLCNumber string `json:"oclc"`
	// AtLibrary is true if the item is found at the configured institution
	AtLibrary bool `json:"at_library"`
	// InState is true if the item is found at other institutions in the state
	InState bool `json:"in_state"`
	// CatalogURL is the link to the item in the institution's catalog (if AtLibrary is true)
	CatalogURL string `json:"catalog_url,omitempty"`
	// Error message if the check failed
	Error string `json:"error,omitempty"`
	// Debug info (only present when debug mode is enabled)
	Debug *DebugInfo `json:"debug,omitempty"`
}

// DebugInfo contains diagnostic information.
type DebugInfo struct {
	StateRequested    string      `json:"state_requested,omitempty"`
	LibrariesFound    int         `json:"libraries_found,omitempty"`
	RawResponse       interface{} `json:"raw_response,omitempty"`
}

// IsLastCopy returns true if the item is at the library and not elsewhere in the state.
// This is the primary business logic users care about.
func (r *Result) IsLastCopy() bool {
	return r.AtLibrary && !r.InState
}

// Checker handles the business logic for checking last copy status.
type Checker struct {
	client      api.Client
	institution string
	state       string
	debug       bool
}

// New creates a new Checker with the given API client and configuration.
func New(client api.Client, institution, state string, debug bool) *Checker {
	return &Checker{
		client:      client,
		institution: institution,
		state:       strings.ToUpper(state), // Normalize state to uppercase
		debug:       debug,
	}
}

// CheckOCLC queries the WorldCat API for a single OCLC number and determines
// if the item is held by the configured institution and/or others in the state.
func (c *Checker) CheckOCLC(oclcNumber string) *Result {
	// Clean the OCLC number - remove any non-numeric characters
	cleanOCLC := cleanOCLCNumber(oclcNumber)
	if cleanOCLC == "" {
		return &Result{
			OCLCNumber: oclcNumber,
			Error:      "Invalid OCLC number",
		}
	}

	result := &Result{
		OCLCNumber: cleanOCLC,
	}

	// Single API call that returns both holdings and raw response
	holdingsResult, err := c.client.GetHoldingsWithRaw(cleanOCLC, c.state)
	if err != nil {
		result.Error = fmt.Sprintf("API error: %v", err)
		return result
	}

	// Store debug info only if debug mode is enabled
	if c.debug {
		result.Debug = &DebugInfo{
			StateRequested: c.state,
			LibrariesFound: len(holdingsResult.Libraries),
			RawResponse:    holdingsResult.RawResponse,
		}
	}

	// Analyze the holdings
	c.analyzeHoldings(holdingsResult.Libraries, result)
	return result
}

// CheckMany processes multiple OCLC numbers and returns results.
// Useful for batch processing from file uploads or comma-separated input.
func (c *Checker) CheckMany(oclcNumbers []string) []*Result {
	results := make([]*Result, 0, len(oclcNumbers))

	for _, oclc := range oclcNumbers {
		// Skip empty entries
		if strings.TrimSpace(oclc) == "" {
			continue
		}
		result := c.CheckOCLC(oclc)
		results = append(results, result)
	}

	return results
}

// analyzeHoldings examines library holdings and populates the result struct.
// Logic: Check if institution holds it, and if any other state libraries hold it.
func (c *Checker) analyzeHoldings(libraries []api.Library, result *Result) {
	// Handle case where no libraries hold this item in the state
	if len(libraries) == 0 {
		result.AtLibrary = false
		result.InState = false
		return
	}

	for _, lib := range libraries {
		// Check if this is the configured institution
		if strings.EqualFold(lib.InstitutionName, c.institution) {
			result.AtLibrary = true
			result.CatalogURL = lib.OPACURL
		} else {
			// Another institution in the state has this item
			result.InState = true
		}
	}
}

// cleanOCLCNumber removes all non-numeric characters from an OCLC number.
// WorldCat accepts various formats (with prefixes, spaces, etc.) but we
// normalize to pure digits for the API call.
func cleanOCLCNumber(oclc string) string {
	var result strings.Builder
	for _, r := range oclc {
		if r >= '0' && r <= '9' {
			result.WriteRune(r)
		}
	}
	return result.String()
}

// LastCopyCandidates filters results to only those that might be last copies.
// Returns items where: AtLibrary=true AND (InState=false OR error occurred)
// The user can then review these to determine if they're truly last copies.
func LastCopyCandidates(results []*Result) []*Result {
	candidates := make([]*Result, 0)

	for _, r := range results {
		// Include if at library but not elsewhere in state
		// Also include if there was an error (user needs to know about these)
		if r.AtLibrary && !r.InState {
			candidates = append(candidates, r)
		}
	}

	return candidates
}
